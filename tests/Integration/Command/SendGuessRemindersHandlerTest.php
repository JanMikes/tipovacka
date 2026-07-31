<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\Command\SendGuessReminders\SendGuessRemindersCommand;
use App\Command\SetNotificationPreference\SetNotificationPreferenceCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Notification;
use App\Entity\SportMatch;
use App\Enum\NotificationType;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

/**
 * The reminder sweep sends ONE aggregated digest per user — never one
 * notification/e-mail per competition. ADMIN sits in five fixture competitions
 * on PUBLIC_SOURCE (Admin liga, both global ones, the premium and the boosts
 * league), so a single near match is exactly the "member of many soutěže"
 * scenario the digest exists for.
 */
final class SendGuessRemindersHandlerTest extends IntegrationTestCase
{
    /** Adds a scheduled PUBLIC_SOURCE match kicking off within the 24 h window (now = 2025-06-15 12:00). */
    private function addNearMatch(string $home = 'Blízký Domácí', string $kickoff = '2025-06-16 10:00:00 UTC'): SportMatch
    {
        $envelope = $this->commandBus()->dispatch(new CreateSportMatchCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeTeam: $home,
            awayTeam: 'Blízký Hosté',
            kickoffAt: new \DateTimeImmutable($kickoff),
            venue: null,
        ));

        $handled = $envelope->last(HandledStamp::class);
        self::assertNotNull($handled);

        /* @var SportMatch */
        return $handled->getResult();
    }

    public function testOneDigestAcrossAllCompetitions(): void
    {
        $this->addNearMatch();

        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        // ONE visible notification for ADMIN despite five affected competitions…
        $visible = $this->visibleReminders(AppFixtures::ADMIN_ID);
        self::assertCount(1, $visible);

        $digest = $visible[0];
        // …spanning competitions, so it carries no single competition and lands on /zapasy.
        self::assertNull($digest->competition);
        self::assertNotNull($digest->url);
        self::assertStringContainsString('/zapasy', $digest->url);
        self::assertSame('Chybí vám 5 tipů', $digest->title);
        self::assertStringContainsString(AppFixtures::PUBLIC_COMPETITION_NAME, $digest->body);
        self::assertStringContainsString(AppFixtures::GLOBAL_COMPETITION_NAME, $digest->body);
        self::assertStringContainsString('nejbližší uzávěrka', $digest->body);

        // One (competition, deadline-day) stamp per competition: 1 visible + 4 markers.
        self::assertSame(5, $this->reminderRowCount(AppFixtures::ADMIN_ID));
        // And exactly one e-mail.
        self::assertSame(1, $this->reminderEmailCountFor(AppFixtures::ADMIN_EMAIL));
    }

    public function testNoReminderOutsideWindow(): void
    {
        // No near match added — every fixture match deadline is > 24 h away.
        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        self::assertSame(0, $this->reminderRowCount(AppFixtures::ADMIN_ID));
    }

    public function testTippedCompetitionDropsFromDigest(): void
    {
        $match = $this->addNearMatch();

        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            sportMatchId: $match->id,
            homeScore: 1,
            awayScore: 0,
        ));

        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        // The tipped soutěž no longer appears; the others still do.
        $visible = $this->visibleReminders(AppFixtures::ADMIN_ID);
        self::assertCount(1, $visible);
        self::assertStringNotContainsString(AppFixtures::PUBLIC_COMPETITION_NAME, $visible[0]->body);
        self::assertStringContainsString(AppFixtures::GLOBAL_COMPETITION_NAME, $visible[0]->body);
    }

    public function testReminderIsIdempotentAcrossRuns(): void
    {
        $this->addNearMatch();

        $this->commandBus()->dispatch(new SendGuessRemindersCommand());
        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        self::assertCount(1, $this->visibleReminders(AppFixtures::ADMIN_ID));
        self::assertSame(1, $this->reminderEmailCountFor(AppFixtures::ADMIN_EMAIL));
    }

    /**
     * A match added later on an ALREADY-reminded deadline-day rides the existing
     * (competition, day) stamp — no second digest for the same day.
     */
    public function testSameDayLaterMatchDoesNotRetrigger(): void
    {
        $this->addNearMatch();
        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        $this->addNearMatch(home: 'Pozdější Domácí', kickoff: '2025-06-16 11:00:00 UTC');
        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        self::assertCount(1, $this->visibleReminders(AppFixtures::ADMIN_ID));
        self::assertSame(1, $this->reminderEmailCountFor(AppFixtures::ADMIN_EMAIL));
    }

    public function testPreferenceOffSuppressesReminder(): void
    {
        $this->addNearMatch();

        $this->commandBus()->dispatch(new SetNotificationPreferenceCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            type: NotificationType::GuessReminder,
            inApp: false,
            email: false,
        ));

        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        self::assertSame(0, $this->reminderRowCount(AppFixtures::ADMIN_ID));
    }

    /**
     * Regression: in-app OFF + email ON. The reminder sweep runs HOURLY, so a
     * channel-dependent dedup would have re-sent the email every hour. The
     * delivery-level stamps (digest row + markers) keep the e-mail to exactly
     * ONE across runs — and nothing surfaces in the (in-app) feed.
     */
    public function testEmailReminderStaysOnceAcrossRunsWhenInAppOff(): void
    {
        $this->addNearMatch();

        $this->commandBus()->dispatch(new SetNotificationPreferenceCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            type: NotificationType::GuessReminder,
            inApp: false,
            email: true,
        ));

        $this->commandBus()->dispatch(new SendGuessRemindersCommand());
        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        self::assertSame(1, $this->reminderEmailCountFor(AppFixtures::ADMIN_EMAIL), 'Email sent once, not re-sent hourly.');
        // Dedup stamps exist (raw rows) but none is feed-visible.
        self::assertSame(5, $this->reminderRowCount(AppFixtures::ADMIN_ID));
        self::assertCount(0, $this->visibleReminders(AppFixtures::ADMIN_ID));
    }

    /**
     * A player whose missing tips sit in a SINGLE soutěž keeps the competition
     * context: title names it, the row links its detail.
     */
    public function testSingleCompetitionDigestKeepsCompetitionContext(): void
    {
        // Near match on the PRIVATE source — only VERIFIED_COMPETITION includes it,
        // and VERIFIED_USER is a member of that competition alone.
        $this->commandBus()->dispatch(new CreateSportMatchCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeTeam: 'Blízký Domácí',
            awayTeam: 'Blízký Hosté',
            kickoffAt: new \DateTimeImmutable('2025-06-16 10:00:00 UTC'),
            venue: null,
        ));

        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        $visible = $this->visibleReminders(AppFixtures::VERIFIED_USER_ID);
        self::assertCount(1, $visible);
        self::assertSame(sprintf('Chybí vám tipy v soutěži %s', AppFixtures::VERIFIED_COMPETITION_NAME), $visible[0]->title);
        self::assertNotNull($visible[0]->competition);
        self::assertSame(AppFixtures::VERIFIED_COMPETITION_ID, $visible[0]->competition->id->toRfc4122());
        self::assertNotNull($visible[0]->url);
        self::assertStringContainsString(AppFixtures::VERIFIED_COMPETITION_ID, $visible[0]->url);
    }

    /**
     * @return list<Notification>
     */
    private function visibleReminders(string $userId): array
    {
        /** @var list<Notification> $result */
        $result = $this->entityManager()->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.type = :type')
            ->andWhere('n.inAppVisible = true')
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('type', NotificationType::GuessReminder)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** All reminder rows including invisible dedup markers. */
    private function reminderRowCount(string $userId): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.type = :type')
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('type', NotificationType::GuessReminder)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Reminder e-mails addressed to the given recipient. */
    private function reminderEmailCountFor(string $recipient): int
    {
        return count(array_filter(
            $this->messengerAsyncTransport()->getSent(),
            static function ($envelope) use ($recipient): bool {
                $message = $envelope->getMessage();

                if (!$message instanceof SendEmailMessage) {
                    return false;
                }

                $email = $message->getMessage();

                if (!$email instanceof Email || !str_contains((string) $email->getSubject(), 'Chybí vám')) {
                    return false;
                }

                foreach ($email->getTo() as $address) {
                    if ($address->getAddress() === $recipient) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }
}
