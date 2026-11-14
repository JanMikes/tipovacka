<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Membership;
use App\Entity\NotificationPreference;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Enum\EmailFrequency;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use App\Service\Notification\EmailPreferenceLinks;
use App\Service\Notification\NotificationMailer;
use App\Service\Notification\NotificationPolicy;
use App\Service\Notification\Notifier;
use App\Tests\Support\IntegrationTestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Uid\Uuid;

final class NotifierTest extends IntegrationTestCase
{
    private function notifier(): Notifier
    {
        /* @var Notifier */
        return self::getContainer()->get(Notifier::class);
    }

    private function user(string $id): User
    {
        return $this->entityManager()->find(User::class, Uuid::fromString($id)) ?? throw new \RuntimeException('user missing');
    }

    private function notificationRepository(): NotificationRepository
    {
        /* @var NotificationRepository */
        return self::getContainer()->get(NotificationRepository::class);
    }

    private function sentEmailCount(): int
    {
        return count(array_filter(
            $this->messengerAsyncTransport()->getSent(),
            static fn ($envelope): bool => $envelope->getMessage() instanceof SendEmailMessage,
        ));
    }

    /**
     * The mails as the recipient gets them. The message logger holds the RENDERED
     * copies; what sits on the async transport is still the bare template + context
     * (Symfony renders it again in the worker).
     *
     * @return list<Email>
     */
    private function sentEmails(): array
    {
        return array_values(array_filter(
            self::getMailerMessages(),
            static fn ($message): bool => $message instanceof Email,
        ));
    }

    public function testImmediateFrequencyCreatesRowAndEmail(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);

        // GuessReminder defaults: in-app ON + email ON.
        $this->notifier()->notify($user, NotificationType::GuessReminder, 'Titulek', 'Tělo zprávy.');
        $this->entityManager()->flush();

        self::assertSame(1, $this->notificationRepository()->countForUser($user->id));
        self::assertSame(1, $this->sentEmailCount());
    }

    public function testDefaultFrequencyIsDailySoALiveReminderIsNeitherMailedNorQueued(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);

        // No settings row = the default (daily summary). A reminder is a statement
        // about NOW: the summary recomputes it, so nothing may wait in a queue.
        $this->notifier()->notify($user, NotificationType::GuessReminder, 'Titulek', 'Tělo zprávy.');
        $this->entityManager()->flush();

        self::assertSame(1, $this->notificationRepository()->countForUser($user->id));
        self::assertSame(0, $this->sentEmailCount());
        self::assertSame([], $this->notificationRepository()->listEmailPending());
    }

    public function testDailyFrequencyQueuesAnOrdinaryNotificationInsteadOfMailingIt(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);

        // CompetitionEnded: e-mail ON by default, an ordinary (Standard) type.
        $this->notifier()->notify($user, NotificationType::CompetitionEnded, 'Soutěž skončila', 'Skončili jste 2.');
        $this->entityManager()->flush();

        self::assertSame(0, $this->sentEmailCount());

        $pending = $this->notificationRepository()->listEmailPending();
        self::assertCount(1, $pending);
        self::assertSame('Soutěž skončila', $pending[0]->title);
        self::assertTrue($pending[0]->inAppVisible);
    }

    public function testImmediateFrequencyMailsAnOrdinaryNotificationAtOnce(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);

        $this->notifier()->notify($user, NotificationType::CompetitionEnded, 'Soutěž skončila', 'Skončili jste 2.');
        $this->entityManager()->flush();

        self::assertSame(1, $this->sentEmailCount());
        self::assertSame([], $this->notificationRepository()->listEmailPending());
    }

    public function testBurstTypeIsQueuedEvenForAnImmediatePlayer(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);
        $this->setPreference($user, NotificationType::MatchEvaluated, inApp: true, email: true);

        // A feed evaluates a whole round at once — never one mail per zápas.
        $this->notifier()->notify($user, NotificationType::MatchEvaluated, 'Vyhodnoceno: A 1:0 B', 'A 1:0 B: získáváte 3 b.');
        $this->notifier()->notify($user, NotificationType::MatchEvaluated, 'Vyhodnoceno: C 2:2 D', 'C 2:2 D: získáváte 1 b.');
        $this->entityManager()->flush();

        self::assertSame(0, $this->sentEmailCount());
        self::assertCount(2, $this->notificationRepository()->listEmailPending());
    }

    public function testUrgentTypeIsMailedAtOnceEvenOnTheDailySummary(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Daily);

        $this->notifier()->notify($user, NotificationType::PremiumChargeUncovered, 'Nepokrytá platba', 'Dobijte kredity.');
        $this->entityManager()->flush();

        self::assertSame(1, $this->sentEmailCount());
        self::assertSame([], $this->notificationRepository()->listEmailPending());
    }

    public function testNeverFrequencySilencesEveryEmailButKeepsTheFeed(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Never);

        $this->notifier()->notify($user, NotificationType::GuessReminder, 'Připomínka', 'Tělo.');
        $this->notifier()->notify($user, NotificationType::CompetitionEnded, 'Konec', 'Tělo.');
        $this->notifier()->notify($user, NotificationType::PremiumChargeUncovered, 'Platba', 'Tělo.');
        $this->entityManager()->flush();

        self::assertSame(3, $this->notificationRepository()->countForUser($user->id));
        self::assertSame(0, $this->sentEmailCount());
        self::assertSame([], $this->notificationRepository()->listEmailPending());
    }

    public function testMutedCompetitionIsSilentOnBothChannels(): void
    {
        $user = $this->user(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);
        $this->mute($user, $competition);
        $before = $this->notificationRepository()->countForUser($user->id);

        $this->notifier()->notify($user, NotificationType::CompetitionEnded, 'Konec', 'Tělo.', competition: $competition, dedupKey: 'k');
        $this->entityManager()->flush();

        self::assertSame($before, $this->notificationRepository()->countForUser($user->id));
        self::assertSame(0, $this->sentEmailCount());
        // No row ⇒ no dedup marker: un-muting later loses nothing.
        self::assertFalse($this->notificationRepository()->existsForDedup($user->id, NotificationType::CompetitionEnded, 'k'));
    }

    public function testMoneyAlertIgnoresTheMute(): void
    {
        $user = $this->user(AppFixtures::VERIFIED_USER_ID);
        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);
        $this->mute($user, $competition);
        $before = $this->notificationRepository()->countForUser($user->id);

        $this->notifier()->notify($user, NotificationType::PremiumBalanceLow, 'Nízký zůstatek', 'Tělo.', competition: $competition);
        $this->entityManager()->flush();

        self::assertSame($before + 1, $this->notificationRepository()->countForUser($user->id));
        self::assertSame(1, $this->sentEmailCount());
    }

    public function testNotificationMailCarriesOneClickUnsubscribeHeadersAndFooterLinks(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);

        $this->notifier()->notify($user, NotificationType::GuessReminder, 'Chybí vám tipy', 'Tělo zprávy.', url: '/zapasy');
        $this->entityManager()->flush();

        $emails = $this->sentEmails();
        self::assertCount(1, $emails);

        /** @var EmailPreferenceLinks $links */
        $links = self::getContainer()->get(EmailPreferenceLinks::class);
        $token = $links->tokenFor($user->id);

        $headers = $emails[0]->getHeaders();
        self::assertSame('List-Unsubscribe=One-Click', $headers->get('List-Unsubscribe-Post')?->getBodyAsString());
        self::assertStringContainsString(
            sprintf('/e-maily/%s/odhlasit/typ-guess_reminder>', $token),
            (string) $headers->get('List-Unsubscribe')?->getBodyAsString(),
        );

        $html = (string) $emails[0]->getHtmlBody();
        self::assertStringContainsString(sprintf('/e-maily/%s?co=typ-guess_reminder', $token), $html);
        self::assertStringContainsString('Odhlásit tento typ', $html);
        // The stored path is host-relative; the mail must carry the host.
        self::assertMatchesRegularExpression('#href="https?://[^"/]+/zapasy"#', $html);
        // A plain-text alternative is generated alongside the HTML.
        self::assertStringContainsString('Tělo zprávy.', (string) $emails[0]->getTextBody());
    }

    public function testEmailOffPreferenceSuppressesEmailButKeepsInApp(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);
        $this->setPreference($user, NotificationType::GuessReminder, inApp: true, email: false);

        $this->notifier()->notify($user, NotificationType::GuessReminder, 'Titulek', 'Tělo.');
        $this->entityManager()->flush();

        self::assertSame(1, $this->notificationRepository()->countForUser($user->id));
        self::assertSame(0, $this->sentEmailCount());
    }

    public function testBothChannelsOffDeliversNothingAndWritesNoRow(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setPreference($user, NotificationType::GuessReminder, inApp: false, email: false);

        $this->notifier()->notify($user, NotificationType::GuessReminder, 'Titulek', 'Tělo.', dedupKey: 'x');
        $this->entityManager()->flush();

        self::assertSame(0, $this->notificationRepository()->countForUser($user->id));
        self::assertCount(0, $this->notificationRepository()->listForUser($user->id, 10));
        self::assertSame(0, $this->sentEmailCount());
    }

    public function testBothChannelsOnYieldsOneFeedRowAndOneEmail(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);

        // GuessReminder defaults: in-app ON + email ON.
        $this->notifier()->notify($user, NotificationType::GuessReminder, 'Titulek', 'Tělo.');
        $this->entityManager()->flush();

        self::assertCount(1, $this->notificationRepository()->listForUser($user->id, 10));
        self::assertSame(1, $this->sentEmailCount());
    }

    /**
     * The email-spam regression: in-app OFF + email ON on a deduped type. The
     * dedup row is now written even though nothing is shown in the feed, so the
     * second notify() with the same key sends NO further email — and the feed
     * stays empty (the row is invisible).
     */
    public function testInAppOffEmailOnDedupSendsExactlyOneEmailAndKeepsFeedEmpty(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);
        $this->setPreference($user, NotificationType::GuessReminder, inApp: false, email: true);

        $this->notifier()->notify($user, NotificationType::GuessReminder, 'A', 'První.', dedupKey: 'reminder:day-1');
        $this->entityManager()->flush();
        $this->notifier()->notify($user, NotificationType::GuessReminder, 'B', 'Druhá.', dedupKey: 'reminder:day-1');
        $this->entityManager()->flush();

        // Exactly one email across the two calls (dedup now guards email-only).
        self::assertSame(1, $this->sentEmailCount());
        // Nothing visible in the bell / center — the dedup row is invisible.
        self::assertCount(0, $this->notificationRepository()->listForUser($user->id, 10));
        self::assertSame(0, $this->notificationRepository()->countForUser($user->id));
        // ... but the delivery-level dedup marker exists and blocks re-sends.
        self::assertTrue($this->notificationRepository()->existsForDedup($user->id, NotificationType::GuessReminder, 'reminder:day-1'));
    }

    public function testDedupKeySuppressesSecondDelivery(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);

        $this->notifier()->notify($user, NotificationType::GuessReminder, 'A', 'První.', dedupKey: 'reminder:day-1');
        $this->entityManager()->flush();
        $this->notifier()->notify($user, NotificationType::GuessReminder, 'B', 'Druhá.', dedupKey: 'reminder:day-1');
        $this->entityManager()->flush();

        self::assertSame(1, $this->notificationRepository()->countForUser($user->id));
        self::assertSame(1, $this->sentEmailCount());
    }

    public function testEmaillessUserGetsInAppButNoEmail(): void
    {
        // ANONYMOUS_USER has no email address.
        $user = $this->user(AppFixtures::ANONYMOUS_USER_ID);
        self::assertNull($user->email);

        $this->notifier()->notify($user, NotificationType::GuessReminder, 'Titulek', 'Tělo.');
        $this->entityManager()->flush();

        self::assertSame(1, $this->notificationRepository()->countForUser($user->id));
        self::assertSame(0, $this->sentEmailCount());
    }

    public function testContentIsPersistedPreRendered(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);

        $this->notifier()->notify(
            $user,
            NotificationType::MatchAdded,
            'Nový zápas: A – B',
            'Do soutěže X přibyl zápas A – B.',
            url: '/oznameni',
        );
        $this->entityManager()->flush();

        $rows = $this->notificationRepository()->listForUser($user->id, 5);
        self::assertCount(1, $rows);
        self::assertSame('Nový zápas: A – B', $rows[0]->title);
        self::assertSame('Do soutěže X přibyl zápas A – B.', $rows[0]->body);
        // Stored host-relative: the origin belongs to whoever renders it, so a wrong
        // `default_uri` can never point the in-app click-through off-domain.
        self::assertSame('/oznameni', $rows[0]->url);
        self::assertSame(NotificationType::MatchAdded, $rows[0]->type);
    }

    public function testNeverThrowsWhenMailerFails(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->setFrequency($user, EmailFrequency::Immediate);

        $failingMailer = new class () implements MailerInterface {
            public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
            {
                throw new class () extends \RuntimeException implements TransportExceptionInterface {
                    public function getDebug(): string
                    {
                        return '';
                    }

                    public function appendDebug(string $debug): void
                    {
                    }
                };
            }
        };

        $notifier = new Notifier(
            $this->notificationRepository(),
            self::getContainer()->get(NotificationPolicy::class),
            new NotificationMailer(
                $failingMailer,
                self::getContainer()->get(EmailPreferenceLinks::class),
                self::getContainer()->get(\App\Repository\NotificationSettingsRepository::class),
            ),
            $this->identityProvider(),
            self::getContainer()->get(ClockInterface::class),
            self::getContainer()->get(LoggerInterface::class),
        );

        // GuessReminder emails by default → the mailer throws, but notify() swallows it.
        $notifier->notify($user, NotificationType::GuessReminder, 'Titulek', 'Tělo.');
        $this->entityManager()->flush();

        // In-app row still written despite the email failure.
        self::assertSame(1, $this->notificationRepository()->countForUser($user->id));
    }

    private function setPreference(User $user, NotificationType $type, bool $inApp, bool $email): void
    {
        $em = $this->entityManager();
        $em->persist(new NotificationPreference(
            id: $this->identityProvider()->next(),
            user: $user,
            type: $type,
            inApp: $inApp,
            email: $email,
            createdAt: \DateTimeImmutable::createFromInterface($this->clock()->now()),
        ));
        $em->flush();
    }

    private function setFrequency(User $user, EmailFrequency $frequency): void
    {
        $em = $this->entityManager();
        $em->persist(new NotificationSettings(
            id: $this->identityProvider()->next(),
            user: $user,
            emailFrequency: $frequency,
            digestHour: NotificationSettings::DEFAULT_DIGEST_HOUR,
            createdAt: \DateTimeImmutable::createFromInterface($this->clock()->now()),
        ));
        $em->flush();
    }

    private function competition(string $id): Competition
    {
        return $this->entityManager()->find(Competition::class, Uuid::fromString($id)) ?? throw new \RuntimeException('competition missing');
    }

    private function mute(User $user, Competition $competition): void
    {
        $membership = $this->entityManager()->getRepository(Membership::class)->findOneBy(['user' => $user, 'competition' => $competition, 'leftAt' => null])
            ?? throw new \RuntimeException('membership missing');
        $membership->muteNotifications();
        $this->entityManager()->flush();
    }
}
