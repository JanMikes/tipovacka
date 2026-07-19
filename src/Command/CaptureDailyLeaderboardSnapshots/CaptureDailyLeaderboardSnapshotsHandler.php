<?php

declare(strict_types=1);

namespace App\Command\CaptureDailyLeaderboardSnapshots;

use App\Command\CaptureLeaderboardSnapshots\CaptureLeaderboardSnapshotsCommand;
use App\Repository\CompetitionRepository;
use App\Repository\GuessEvaluationRepository;
use App\Repository\LeaderboardSnapshotRepository;
use App\Service\PragueCalendar;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The daily sweep. A competition is snapshotted today only when an evaluation
 * exists that is newer than its last snapshot (or it has evaluations but was
 * never snapshotted) — competitions that did not score anything since yesterday
 * are skipped, keeping the sweep cheap and the history free of no-op days.
 */
#[AsMessageHandler]
final readonly class CaptureDailyLeaderboardSnapshotsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private LeaderboardSnapshotRepository $snapshotRepository,
        private GuessEvaluationRepository $evaluationRepository,
        private MessageBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CaptureDailyLeaderboardSnapshotsCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $today = PragueCalendar::day($now);

        foreach ($this->competitionRepository->findAllActive() as $competition) {
            $lastSnapshotAt = $this->snapshotRepository->latestSnapshotMomentFor($competition->id);

            if (!$this->evaluationRepository->hasAnyForCompetitionSince($competition->id, $lastSnapshotAt)) {
                continue;
            }

            $this->commandBus->dispatch(new CaptureLeaderboardSnapshotsCommand(
                competitionId: $competition->id,
                day: $today,
            ));
        }
    }
}
