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
        /**
         * The round this board is scoped to — set only for
         * {@see \App\Enum\LeaderboardTimeFilter::LastRound}, and null there too
         * when the competition has no round-labelled match (the board then
         * silently falls back to the unscoped totals). Lets the UI name the
         * round it is showing, or hide the tab entirely.
         */
        public ?string $roundLabel = null,
    ) {
    }
}
