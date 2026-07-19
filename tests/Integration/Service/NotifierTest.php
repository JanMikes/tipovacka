<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\NotificationPreference;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Service\Notification\Notifier;
use App\Tests\Support\IntegrationTestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
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

    public function testBothChannelsOnCreatesRowAndEmail(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);

        // GuessReminder defaults: in-app ON + email ON.
        $this->notifier()->notify($user, NotificationType::GuessReminder, 'Titulek', 'Tělo zprávy.');
        $this->entityManager()->flush();

        self::assertSame(1, $this->notificationRepository()->countForUser($user->id));
        self::assertSame(1, $this->sentEmailCount());
    }

    public function testEmailOffPreferenceSuppressesEmailButKeepsInApp(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);
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
            url: 'https://wtips.cz/portal/oznameni',
        );
        $this->entityManager()->flush();

        $rows = $this->notificationRepository()->listForUser($user->id, 5);
        self::assertCount(1, $rows);
        self::assertSame('Nový zápas: A – B', $rows[0]->title);
        self::assertSame('Do soutěže X přibyl zápas A – B.', $rows[0]->body);
        self::assertSame('https://wtips.cz/portal/oznameni', $rows[0]->url);
        self::assertSame(NotificationType::MatchAdded, $rows[0]->type);
    }

    public function testNeverThrowsWhenMailerFails(): void
    {
        $user = $this->user(AppFixtures::SECOND_VERIFIED_USER_ID);

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
            self::getContainer()->get(NotificationPreferenceRepository::class),
            $this->identityProvider(),
            $failingMailer,
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
}
