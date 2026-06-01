<?php

declare(strict_types=1);

namespace App\Query\GetMatchPickDistribution;

final readonly class MatchPickDistributionResult
{
    public function __construct(
        public int $homeWinCount,
        public int $drawCount,
        public int $awayWinCount,
        public int $total,
        public int $homeWinPercent,
        public int $drawPercent,
        public int $awayWinPercent,
    ) {
    }
}
