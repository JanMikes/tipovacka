<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionLeaderboard;

final readonly class CompetitionLeaderboardResult
{
    /**
     * @param list<LeaderboardRow> $rows
     */
    public function __construct(
        public array $rows,
        public bool $matchSourceCompleted,
        /**
         * Whether the Δ column is meaningful for this result — true only for the
         * all-time filter (snapshots are all-time; a windowed board re-ranks and
         * would make an all-time Δ nonsensical, so the UI hides the column).
         */
        public bool $showDelta = true,
    ) {
    }
}
