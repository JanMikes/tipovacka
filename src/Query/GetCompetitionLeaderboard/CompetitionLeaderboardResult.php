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
    ) {
    }
}
