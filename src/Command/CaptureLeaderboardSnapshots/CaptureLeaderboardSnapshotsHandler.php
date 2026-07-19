<?php

declare(strict_types=1);

namespace App\Command\CaptureLeaderboardSnapshots;

use App\Entity\LeaderboardSnapshot;
use App\Entity\User;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Repository\LeaderboardSnapshotRepository;
use App\Service\Identity\ProvideIdentity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Freezes the competition's current all-time leaderboard (reusing
 * {@see GetCompetitionLeaderboard}, so tie-resolution overrides are captured as
 * the authoritative rank) into a {@see LeaderboardSnapshot} row per member for
 * the given Prague day. The upsert replaces that day's rows, so this is safe to
 * re-run (recalculation re-captures today; the daily sweep captures once).
 */
#[AsMessageHandler]
final readonly class CaptureLeaderboardSnapshotsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private LeaderboardSnapshotRepository $snapshotRepository,
        private QueryBus $queryBus,
        private EntityManagerInterface $entityManager,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CaptureLeaderboardSnapshotsCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $competition = $this->competitionRepository->get($command->competitionId);

        // All-time standings (the default filter) — Δ is always relative to the
        // overall board, never a windowed one.
        $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard(competitionId: $competition->id));

        $snapshots = [];

        foreach ($leaderboard->rows as $row) {
            // Each row is an active member, so the reference is always resolvable —
            // getReference avoids loading the full User just to hold the FK.
            $user = $this->entityManager->getReference(User::class, $row->userId);
            \assert($user instanceof User);

            $snapshots[] = new LeaderboardSnapshot(
                id: $this->identity->next(),
                competition: $competition,
                user: $user,
                day: $command->day,
                points: $row->totalPoints,
                rank: $row->rank,
                createdAt: $now,
            );
        }

        $this->snapshotRepository->upsertDay($competition->id, $command->day, $snapshots);
    }
}
