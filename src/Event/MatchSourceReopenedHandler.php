<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\LeaderboardTieResolutionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MatchSourceReopenedHandler
{
    public function __construct(
        private LeaderboardTieResolutionRepository $resolutionRepository,
    ) {
    }

    public function __invoke(MatchSourceReopened $event): void
    {
        // Manual tie-break ranks are only meaningful for frozen final standings;
        // a reopened source will move points again, so drop them for every
        // competition attached to the source. Leaderboards fall back to pure
        // point ordering until the source completes and ties are re-resolved.
        $this->resolutionRepository->deleteForMatchSource($event->matchSourceId);
    }
}
