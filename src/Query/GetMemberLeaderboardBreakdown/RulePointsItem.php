<?php

declare(strict_types=1);

namespace App\Query\GetMemberLeaderboardBreakdown;

final readonly class RulePointsItem
{
    public function __construct(
        public string $ruleIdentifier,
        public int $points,
    ) {
    }
}
