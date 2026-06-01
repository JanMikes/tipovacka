<?php

declare(strict_types=1);

namespace App\Query\GetMatchRanking;

final readonly class MatchRankingResult
{
    /**
     * @param list<MatchRankingRow> $rows
     */
    public function __construct(
        public array $rows,
    ) {
    }
}
