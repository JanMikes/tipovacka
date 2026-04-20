<?php

declare(strict_types=1);

namespace App\Query\GetGroupLeaderboard;

final readonly class GroupLeaderboardResult
{
    /**
     * @param list<LeaderboardRow> $rows
     */
    public function __construct(
        public array $rows,
        public bool $tournamentFinished,
    ) {
    }
}
