<?php

declare(strict_types=1);

namespace App\Query\GetMemberLeaderboardBreakdown;

final readonly class RulePointsItem
{
    public function __construct(
        public string $ruleIdentifier,
        /** Czech rule label from the RuleRegistry (never the raw identifier). */
        public string $label,
        /** Stored product: multiplier × configured points at evaluation time. */
        public int $points,
        /**
         * Display multiplier derived from the competition's CURRENT configured
         * points (points ÷ unit when cleanly divisible, else 1). Rule changes
         * trigger full recalculation, so config and evaluations stay in sync.
         */
        public int $multiplier,
    ) {
    }
}
