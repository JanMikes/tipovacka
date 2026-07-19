<?php

declare(strict_types=1);

namespace App\Event;

use App\Command\CaptureLeaderboardSnapshots\CaptureLeaderboardSnapshotsCommand;
use App\Service\PragueCalendar;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Event-driven snapshot re-captures for TODAY (Prague), both funnelling through
 * {@see captureToday} which dispatches the async capture command:
 *
 *  - {@see onCompetitionEnded}: an immediate FINAL snapshot when the competition
 *    is over, so the closing standings are frozen even if 03:00 is far off.
 *  - {@see onPointsRecalculated}: after a rules/selection recalculation, today's
 *    snapshot is refreshed so Δ reflects the corrected points. Prior days stay
 *    intact (the upsert only touches today).
 *
 * The command is async precisely so this never nests a snapshot write inside the
 * triggering event's transaction.
 */
final readonly class CaptureLeaderboardSnapshotOnDomainEventHandler
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    #[AsMessageHandler]
    public function onCompetitionEnded(CompetitionEnded $event): void
    {
        $this->captureToday($event->competitionId);
    }

    #[AsMessageHandler]
    public function onPointsRecalculated(CompetitionPointsRecalculated $event): void
    {
        $this->captureToday($event->competitionId);
    }

    private function captureToday(Uuid $competitionId): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $this->commandBus->dispatch(new CaptureLeaderboardSnapshotsCommand(
            competitionId: $competitionId,
            day: PragueCalendar::day($now),
        ));
    }
}
