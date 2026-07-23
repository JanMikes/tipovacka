<?php

declare(strict_types=1);

namespace App\Query\GetMatchPickDistribution;

final readonly class MatchPickDistributionResult
{
    /**
     * Percentages are a pure function of the counts — derive them here so the
     * single-match and batch queries can never disagree.
     */
    public static function fromCounts(int $homeWinCount, int $drawCount, int $awayWinCount, int $total): self
    {
        return new self(
            homeWinCount: $homeWinCount,
            drawCount: $drawCount,
            awayWinCount: $awayWinCount,
            total: $total,
            homeWinPercent: $total > 0 ? (int) round($homeWinCount * 100 / $total) : 0,
            drawPercent: $total > 0 ? (int) round($drawCount * 100 / $total) : 0,
            awayWinPercent: $total > 0 ? (int) round($awayWinCount * 100 / $total) : 0,
        );
    }

    public static function empty(): self
    {
        return self::fromCounts(0, 0, 0, 0);
    }

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
