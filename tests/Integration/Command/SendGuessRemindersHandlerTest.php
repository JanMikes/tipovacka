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
use App\Repository\NotificationRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

final class SendGuessRemindersHandlerTest extends IntegrationTestCase
{
    /** Adds a scheduled PUBLIC_SOURCE match kicking off within the 24 h window (now = 2025-06-15 12:00). */
    private function addNearMatch(): SportMatch
    {
        $envelope = $this->commandBus()->dispatch(new CreateSportMatchCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeTeam: 'Blízký Domácí',
            awayTeam: 'Blízký Hosté',
            kickoffAt: new \DateTimeImmutable('2025-06-16 10:00:00 UTC'),
            venue: null,
        ));

        $handled = $envelope->last(HandledStamp::class);
        self::assertNotNull($handled);

        /* @var SportMatch */
        return $handled->getResult();
    }

    public function testReminderCreatedForMatchWithinWindow(): void
    {
        $this->addNearMatch();

        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        self::assertSame(1, $this->reminderCount(AppFixtures::ADMIN_ID, AppFixtures::PUBLIC_COMPETITION_ID));

        $notification = $this->reminder(AppFixtures::ADMIN_ID, AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertNotNull($notification);
        self::assertStringContainsString('chybí', $notification->body);
        self::assertStringContainsString('1 zápas', $notification->body);
    }

    public function testNoReminderOutsideWindow(): void
    {
        // No near match added — every fixture match is > 24 h away.
        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        self::assertSame(0, $this->reminderCount(AppFixtures::ADMIN_ID, AppFixtures::PUBLIC_COMPETITION_ID));
    }

    public function testNoReminderWhenAlreadyTipped(): void
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

        self::assertSame(0, $this->reminderCount(AppFixtures::ADMIN_ID, AppFixtures::PUBLIC_COMPETITION_ID));
    }

    public function testReminderIsIdempotentAcrossRuns(): void
    {
        $this->addNearMatch();

        $this->commandBus()->dispatch(new SendGuessRemindersCommand());
        $this->commandBus()->dispatch(new SendGuessRemindersCommand());

        self::assertSame(1, $this->reminderCount(AppFixtures::ADMIN_ID, AppFixtures::PUBLIC_COMPETITION_ID));
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

        self::assertSame(0, $this->reminderCount(AppFixtures::ADMIN_ID, AppFixtures::PUBLIC_COMPETITION_ID));
    }

    /**
     * Regression: in-app OFF + email ON. The reminder sweep runs HOURLY, so a
     * channel-dependent dedup would have re-sent the email every hour. The dedup
     * marker is now delivery-level, so the PUBLIC_COMPETITION reminder email is
     * sent exactly ONCE across runs — and nothing surfaces in the (in-app) feed.
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

        // Exactly one email for this competition's reminder across two sweeps.
        self::assertSame(1, $this->reminderEmailCountFor(AppFixtures::PUBLIC_COMPETITION_NAME), 'Email sent once, not re-sent hourly.');
        // Delivery-level dedup row exists (raw count) ...
        self::assertSame(1, $this->reminderCount(AppFixtures::ADMIN_ID, AppFixtures::PUBLIC_COMPETITION_ID));
        // ... but no guess reminder surfaces in the (in-app-off) feed.
        $visibleReminders = array_filter(
            $this->notifications()->listForUser(Uuid::fromString(AppFixtures::ADMIN_ID), 50),
            static fn (Notification $notification): bool => NotificationType::GuessReminder === $notification->type,
        );
        self::assertCount(0, $visibleReminders);
    }

    private function notifications(): NotificationRepository
    {
        /* @var NotificationRepository */
        return self::getContainer()->get(NotificationRepository::class);
    }

    /** Emails whose subject names the given competition (the reminder subject). */
    private function reminderEmailCountFor(string $competitionName): int
    {
        return count(array_filter(
            $this->messengerAsyncTransport()->getSent(),
            static function ($envelope) use ($competitionName): bool {
                $message = $envelope->getMessage();

                if (!$message instanceof SendEmailMessage) {
                    return false;
                }

                $email = $message->getMessage();

                return $email instanceof Email && str_contains((string) $email->getSubject(), $competitionName);
            },
        ));
    }

    private function reminderCount(string $userId, string $competitionId): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.competition = :competitionId')
            ->andWhere('n.type = :type')
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->setParameter('type', NotificationType::GuessReminder)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function reminder(string $userId, string $competitionId): ?Notification
    {
        return $this->entityManager()->createQueryBuilder()
            ->select('n')
            ->from(Notification::class, 'n')
            ->where('n.user = :userId')
            ->andWhere('n.competition = :competitionId')
            ->andWhere('n.type = :type')
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->setParameter('type', NotificationType::GuessReminder)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
