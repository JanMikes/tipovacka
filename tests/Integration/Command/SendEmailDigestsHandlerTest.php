<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\Command\MuteCompetitionNotifications\MuteCompetitionNotificationsCommand;
use App\Command\SendEmailDigests\SendEmailDigestsCommand;
use App\Command\SetNotificationPreference\SetNotificationPreferenceCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\User;
use App\Enum\EmailFrequency;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use App\Repository\NotificationSettingsRepository;
use App\Service\Notification\Notifier;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

/**
 * The hourly e-mail sweep: many notifications, ONE mail per player.
 *
 *  - „průběžně": burst types (a feed finishing a whole round) are collapsed
 *    into one mail instead of one per zápas;
 *  - „jednou denně" (the default): nothing leaves before the player's hour, at
 *    most one summary per Prague day, with the missing tips computed fresh.
 *
 * The test clock is 2025-06-15 12:00 UTC = 14:00 in Prague, i.e. past the
 * default 08:00 summary hour.
 */
final class SendEmailDigestsHandlerTest extends IntegrationTestCase
{
    private const string USER = AppFixtures::VERIFIED_USER_ID;

    // ── „průběžně": the hourly collapse ─────────────────────────────────────

    public function testEvaluatingAMatchQueuesTheMailButSendsNoneOnItsOwn(): void
    {
        $this->setEmailFrequency(self::USER, EmailFrequency::Immediate);
        $this->enableEmail(NotificationType::MatchEvaluated);

        $this->guessAndFinish();

        self::assertCount(1, $this->notifications()->listEmailPending());
        self::assertCount(0, $this->mails(), 'a burst type is never mailed from the event handler');
    }

    public function testSweepSendsOneMailAndASecondSweepSendsNothing(): void
    {
        $this->setEmailFrequency(self::USER, EmailFrequency::Immediate);
        $this->enableEmail(NotificationType::MatchEvaluated);
        $this->guessAndFinish();

        $this->sweep();
        $this->sweep();

        self::assertCount(1, $this->mails());
        self::assertSame([], $this->notifications()->listEmailPending());
    }

    public function testABurstBecomesOneDigestGroupedByCompetition(): void
    {
        $this->setEmailFrequency(self::USER, EmailFrequency::Immediate);
        $this->enableEmail(NotificationType::MatchEvaluated);
        $competition = $this->competition();

        foreach (['A 1:0 B', 'C 2:2 D', 'E 0:3 F'] as $i => $score) {
            $this->notifier()->notify(
                $this->user(),
                NotificationType::MatchEvaluated,
                sprintf('Vyhodnoceno: %s', $score),
                sprintf('%s: získáváte %d b., jste 1. v soutěži %s', $score, $i, $competition->name),
                url: '/zapasy',
                competition: $competition,
                dedupKey: sprintf('match_evaluated:test-%d', $i),
            );
        }
        $this->entityManager()->flush();

        $this->sweep();

        $mails = $this->mails();
        self::assertCount(1, $mails);
        self::assertSame('Vyhodnoceny 3 zápasy', $mails[0]->getSubject());

        $html = (string) $mails[0]->getHtmlBody();
        self::assertStringContainsString($competition->name, $html);
        self::assertStringContainsString('A 1:0 B: získáváte 0 b., jste 1.', $html);
        // The soutěž is the section heading — not repeated on every line under it.
        self::assertStringNotContainsString('jste 1. v soutěži', $html);
        // One type in the mail ⇒ the mail client's button switches off that type.
        self::assertStringContainsString('/odhlasit/typ-match_evaluated>', (string) $mails[0]->getHeaders()->get('List-Unsubscribe')?->getBodyAsString());
    }

    /** `match_evaluated` defaults to in-app only — nobody asked, nobody is mailed. */
    public function testSweepRespectsTheTypePreference(): void
    {
        $this->setEmailFrequency(self::USER, EmailFrequency::Immediate);
        $this->guessAndFinish();

        $this->sweep();

        self::assertCount(0, $this->mails());
    }

    // ── „jednou denně": the daily summary ───────────────────────────────────

    public function testDailySummaryCollectsQueuedNewsOncePerDay(): void
    {
        // No settings row: the default frequency IS the daily summary, at 08:00.
        $this->queueCompetitionEnded('první');

        $this->sweep();

        $mails = $this->mails();
        self::assertCount(1, $mails);
        self::assertStringContainsString('Soutěž první skončila', (string) $mails[0]->getHtmlBody());

        // More news later the same day waits for tomorrow's summary…
        $this->queueCompetitionEnded('druhá');
        $this->sweep();

        self::assertCount(1, $this->mails());
        self::assertCount(1, $this->notifications()->listEmailPending());

        // …and goes out with it.
        $this->clockAt('2025-06-16 07:00:00 UTC');
        $this->sweep();

        self::assertCount(2, $this->mails());
        self::assertSame([], $this->notifications()->listEmailPending());
    }

    public function testDailySummaryWaitsForThePlayersHour(): void
    {
        // 14:00 in Prague now; this player wants the summary from 20:00.
        $this->setEmailFrequency(self::USER, EmailFrequency::Daily, 20);
        $this->queueCompetitionEnded('první');

        $this->sweep();
        self::assertCount(0, $this->mails());

        $this->clockAt('2025-06-15 18:10:00 UTC');
        $this->sweep();
        self::assertCount(1, $this->mails());
    }

    public function testDailySummaryStatesTheTipsStillMissingAndCoversTheNextMorning(): void
    {
        // Kickoff 30 h away — outside the hourly reminder's 24 h, inside the summary's 36 h.
        // The soutěž has not started, so the new first kickoff is the deadline of BOTH
        // its open matches (tips lock at competition start): two tips are missing.
        $this->addMatch('2025-06-16 18:00:00 UTC');

        $this->sweep();

        $mails = $this->mails();
        self::assertCount(1, $mails);
        self::assertSame('Chybí vám 2 tipy', $mails[0]->getSubject());

        $html = (string) $mails[0]->getHtmlBody();
        self::assertStringContainsString(AppFixtures::VERIFIED_COMPETITION_NAME, $html);
        self::assertStringContainsString('Chybí 2 tipy', $html);
        self::assertStringContainsString('Denní souhrn', $html);
        // Leaving the daily summary means leaving notification e-mail.
        self::assertStringContainsString('/odhlasit/vse>', (string) $mails[0]->getHeaders()->get('List-Unsubscribe')?->getBodyAsString());

        // The summary is stamped: no second one today.
        $settings = self::getContainer()->get(NotificationSettingsRepository::class)->findForUser(Uuid::fromString(self::USER));
        self::assertNotNull($settings?->lastDigestAt);

        $this->sweep();
        self::assertCount(1, $this->mails());
    }

    public function testADayWithNothingToSaySendsNothing(): void
    {
        $this->sweep();

        self::assertCount(0, $this->mails());
        self::assertNull(self::getContainer()->get(NotificationSettingsRepository::class)->findForUser(Uuid::fromString(self::USER)));
    }

    public function testMutedCompetitionIsLeftOutOfTheSummary(): void
    {
        $this->addMatch('2025-06-16 18:00:00 UTC');
        $this->commandBus()->dispatch(new MuteCompetitionNotificationsCommand(
            Uuid::fromString(self::USER),
            Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            muted: true,
        ));

        $this->sweep();

        self::assertCount(0, $this->mails());
    }

    public function testReminderSwitchedOffByEmailKeepsMissingTipsOutOfTheSummary(): void
    {
        $this->addMatch('2025-06-16 18:00:00 UTC');
        $this->commandBus()->dispatch(new SetNotificationPreferenceCommand(
            Uuid::fromString(self::USER),
            NotificationType::GuessReminder,
            inApp: true,
            email: false,
        ));

        $this->sweep();

        self::assertCount(0, $this->mails());
    }

    // ── Opting out between queueing and sending ─────────────────────────────

    public function testARowQueuedBeforeAnUnsubscribeIsNeverMailed(): void
    {
        $this->queueCompetitionEnded('první');

        // The player switches the type's e-mail off before the sweep runs.
        $this->commandBus()->dispatch(new SetNotificationPreferenceCommand(
            Uuid::fromString(self::USER),
            NotificationType::CompetitionEnded,
            inApp: true,
            email: false,
        ));

        $this->sweep();

        self::assertCount(0, $this->mails());
        self::assertSame([], $this->notifications()->listEmailPending(), 'settled, so it cannot come back later');
    }

    public function testSwitchingToNoEmailsDropsTheQueue(): void
    {
        $this->queueCompetitionEnded('první');
        $this->setEmailFrequency(self::USER, EmailFrequency::Never);

        $this->sweep();

        self::assertCount(0, $this->mails());
        self::assertSame([], $this->notifications()->listEmailPending());
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function sweep(): void
    {
        $this->commandBus()->dispatch(new SendEmailDigestsCommand());
    }

    /**
     * Notification mails as the recipient gets them (rendered), addressed to the test user.
     *
     * @return list<Email>
     */
    private function mails(): array
    {
        return array_values(array_filter(
            self::getMailerMessages(),
            static fn ($message): bool => $message instanceof Email
                && AppFixtures::VERIFIED_USER_EMAIL === ($message->getTo()[0] ?? null)?->getAddress(),
        ));
    }

    private function queueCompetitionEnded(string $name): void
    {
        // CompetitionEnded mails by default and is an ordinary type: queued for a daily player.
        $this->notifier()->notify(
            $this->user(),
            NotificationType::CompetitionEnded,
            sprintf('Soutěž %s skončila', $name),
            'Skončili jste na 2. místě.',
            url: '/souteze',
            dedupKey: sprintf('competition_ended:test-%s', $name),
        );
        $this->entityManager()->flush();
    }

    private function enableEmail(NotificationType $type): void
    {
        $this->commandBus()->dispatch(new SetNotificationPreferenceCommand(
            Uuid::fromString(self::USER),
            $type,
            inApp: true,
            email: true,
        ));
    }

    /** A scheduled match on the PRIVATE source — only VERIFIED_COMPETITION includes it. */
    private function addMatch(string $kickoff): void
    {
        $this->commandBus()->dispatch(new CreateSportMatchCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            editorId: Uuid::fromString(self::USER),
            homeTeam: 'Blízký Domácí',
            awayTeam: 'Blízký Hosté',
            kickoffAt: new \DateTimeImmutable($kickoff),
            venue: null,
        ));
    }

    private function guessAndFinish(): void
    {
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(self::USER),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            editorId: Uuid::fromString(self::USER),
            homeScore: 2,
            awayScore: 1,
        ));
    }

    private function clockAt(string $moment): void
    {
        $clock = $this->clock();
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->modify($moment);
    }

    private function user(): User
    {
        return $this->entityManager()->find(User::class, Uuid::fromString(self::USER)) ?? throw new \RuntimeException('user missing');
    }

    private function competition(): Competition
    {
        return $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ?? throw new \RuntimeException('competition missing');
    }

    private function notifier(): Notifier
    {
        /* @var Notifier */
        return self::getContainer()->get(Notifier::class);
    }

    private function notifications(): NotificationRepository
    {
        /* @var NotificationRepository */
        return self::getContainer()->get(NotificationRepository::class);
    }
}
