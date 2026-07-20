<?php

declare(strict_types=1);

namespace App\Tests\Integration\Console;

use App\Command\CaptureLeaderboardSnapshots\CaptureLeaderboardSnapshotsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\LeaderboardSnapshot;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Uuid;

/**
 * The host-cron console entry point {@see \App\Console\CaptureDailyLeaderboardSnapshotsCommand}
 * (`app:leaderboard:capture-snapshots`) must dispatch the daily sweep, which fans
 * out a per-competition async capture command for every competition with new
 * evaluations — mirrors the CaptureDailyLeaderboardSnapshotsHandlerTest scenario.
 */
final class CaptureDailyLeaderboardSnapshotsCommandTest extends IntegrationTestCase
{
    public function testCommandEnqueuesAndCapturesSnapshotsForDueCompetitions(): void
    {
        $async = $this->messengerAsyncTransport();
        $async->reset();

        $tester = $this->execute('app:leaderboard:capture-snapshots');
        $tester->assertCommandIsSuccessful();

        // PUBLIC_COMPETITION has the fixture evaluation but no snapshot ⇒ due;
        // FREE_GLOBAL_COMPETITION has no evaluations at all ⇒ never snapshotted.
        $captured = [];
        foreach ($async->getSent() as $envelope) {
            $message = $envelope->getMessage();

            if ($message instanceof CaptureLeaderboardSnapshotsCommand) {
                $captured[] = $message->competitionId->toRfc4122();
                // Locally handle the fanned-out async capture command.
                $this->commandBus()->dispatch(new Envelope($message, [new ReceivedStamp('async')]));
            }
        }

        self::assertContains(AppFixtures::PUBLIC_COMPETITION_ID, $captured);
        self::assertNotContains(AppFixtures::FREE_GLOBAL_COMPETITION_ID, $captured);

        // The fan-out actually wrote today's (Prague) snapshot for the due competition.
        self::assertCount(1, $this->loadSnapshots(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertCount(0, $this->loadSnapshots(AppFixtures::FREE_GLOBAL_COMPETITION_ID));
    }

    /**
     * @return list<LeaderboardSnapshot>
     */
    private function loadSnapshots(string $competitionId): array
    {
        $em = $this->entityManager();
        $em->clear();

        /** @var list<LeaderboardSnapshot> $rows */
        $rows = $em->createQueryBuilder()
            ->select('s')
            ->from(LeaderboardSnapshot::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->getQuery()
            ->getResult();

        return $rows;
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
