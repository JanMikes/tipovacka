<?php

declare(strict_types=1);

namespace App\Query\GetMatchRanking;

final readonly class MatchRankingResult
{
    /**
     * @param list<MatchRankingRow> $rows
     * @param bool                  $isScored whether the rows carry points at all. False until the
     *                                        match is finished and its guesses evaluated — the rows
     *                                        are then a plain alphabetical tip board with no ranks
     */
    public function __construct(
        public array $rows,
        public bool $isScored = false,
    ) {
    }
}
