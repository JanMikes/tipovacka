<?php

declare(strict_types=1);

namespace App\Tests\Integration\Console;

use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * The host-cron console entry point {@see \App\Console\SendGuessRemindersCommand}
 * (`app:guess-reminders:send`) must dispatch the reminder sweep and produce its
 * side effect — mirrors the SendGuessRemindersHandlerTest scenario.
 */
final class SendGuessRemindersCommandTest extends IntegrationTestCase
{
    public function testCommandCreatesReminderForUntippedUpcomingMatch(): void
    {
        // A scheduled PUBLIC_SOURCE match kicking off within the 24 h window
        // (now = 2025-06-15 12:00), untipped by ADMIN ⇒ one reminder is due.
        $this->addNearMatch();

        $tester = $this->execute('app:guess-reminders:send');

        $tester->assertCommandIsSuccessful();

        self::assertSame(1, $this->reminderCount(AppFixtures::ADMIN_ID, AppFixtures::PUBLIC_COMPETITION_ID));
    }

    /** Adds a scheduled PUBLIC_SOURCE match kicking off within the 24 h window. */
    private function addNearMatch(): void
    {
        $this->commandBus()->dispatch(new CreateSportMatchCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeTeam: 'Blízký Domácí',
            awayTeam: 'Blízký Hosté',
            kickoffAt: new \DateTimeImmutable('2025-06-16 10:00:00 UTC'),
            venue: null,
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

    private function execute(string $name): CommandTester
    {
        // Reuse the already-booted kernel (idempotent — keeps the DAMA test
        // transaction and any prior setup dispatches intact; a re-boot would drop them).
        self::getContainer();
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $application = new Application($kernel);
        self::assertTrue($application->has($name), sprintf('Command "%s" must be registered.', $name));

        $tester = new CommandTester($application->find($name));
        $tester->execute([]);

        return $tester;
    }
}
