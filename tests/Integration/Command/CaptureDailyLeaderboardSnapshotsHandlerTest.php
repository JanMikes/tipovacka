<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CaptureDailyLeaderboardSnapshots\CaptureDailyLeaderboardSnapshotsCommand;
use App\Command\CaptureLeaderboardSnapshots\CaptureLeaderboardSnapshotsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\LeaderboardSnapshot;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Uuid;

/**
 * The daily snapshot sweep
 * ({@see \App\Command\CaptureDailyLeaderboardSnapshots\CaptureDailyLeaderboardSnapshotsHandler}).
 */
final class CaptureDailyLeaderboardSnapshotsHandlerTest extends IntegrationTestCase
{
    public function testSnapshotsCompetitionsWithEvaluationsAndSkipsThoseWithout(): void
    {
        // PUBLIC_COMPETITION has the fixture evaluation but no snapshot yet ⇒ due.
        // FREE_GLOBAL_COMPETITION has no evaluations at all ⇒ never snapshotted.
        $captured = $this->runDailySweep();

        self::assertContains(AppFixtures::PUBLIC_COMPETITION_ID, $captured);
        self::assertNotContains(AppFixtures::FREE_GLOBAL_COMPETITION_ID, $captured);

        $publicSnapshots = $this->loadSnapshots(AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertCount(1, $publicSnapshots);
        self::assertSame('2025-06-15', $publicSnapshots[0]->day->format('Y-m-d'), 'Snapshot uses the Prague day.');
        self::assertSame(1, $publicSnapshots[0]->rank);
        self::assertSame(3, $publicSnapshots[0]->points);

        self::assertCount(0, $this->loadSnapshots(AppFixtures::FREE_GLOBAL_COMPETITION_ID));
    }

    public function testCompetitionWithNoNewEvaluationsSinceLastSnapshotIsSkipped(): void
    {
        // First sweep captures PUBLIC_COMPETITION (its evaluation is newer than "never").
        self::assertContains(AppFixtures::PUBLIC_COMPETITION_ID, $this->runDailySweep());
        self::assertCount(1, $this->loadSnapshots(AppFixtures::PUBLIC_COMPETITION_ID));

        // Same clock, no new evaluation since the snapshot ⇒ the second sweep skips it,
        // so no duplicate day/row is written.
        self::assertNotContains(AppFixtures::PUBLIC_COMPETITION_ID, $this->runDailySweep());
        self::assertCount(1, $this->loadSnapshots(AppFixtures::PUBLIC_COMPETITION_ID));
    }

    public function testSeededCompetitionWithoutNewEvaluationsIsNotReSnapshotted(): void
    {
        // VERIFIED_COMPETITION has fixture snapshots (createdAt = now) but no
        // evaluations ⇒ nothing new since ⇒ skipped, its 3 seeded rows untouched.
        self::assertNotContains(AppFixtures::VERIFIED_COMPETITION_ID, $this->runDailySweep());
        self::assertCount(3, $this->loadSnapshots(AppFixtures::VERIFIED_COMPETITION_ID));
    }

    /**
     * Dispatches the daily sweep and locally handles the async per-competition
     * capture commands it emits.
     *
     * @return list<string> competition ids that were snapshotted (RFC-4122)
     */
    private function runDailySweep(): array
    {
        $async = $this->messengerAsyncTransport();
        $async->reset();

        $this->commandBus()->dispatch(new CaptureDailyLeaderboardSnapshotsCommand());

        $captured = [];

        foreach ($async->getSent() as $envelope) {
            $message = $envelope->getMessage();

            if ($message instanceof CaptureLeaderboardSnapshotsCommand) {
                $captured[] = $message->competitionId->toRfc4122();
                $this->commandBus()->dispatch(new Envelope($message, [new ReceivedStamp('async')]));
            }
        }

        return $captured;
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
            ->orderBy('s.rank', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
