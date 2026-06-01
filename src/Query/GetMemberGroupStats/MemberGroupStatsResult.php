<?php

declare(strict_types=1);

namespace App\Query\GetMemberGroupStats;

final readonly class MemberGroupStatsResult
{
    public function __construct(
        public int $rank,
        public int $totalMembers,
        public int $totalPoints,
        public int $evaluatedCount,
        public int $scoredCount,
        public int $exactCount,
        public int $partialCount,
        public int $accuracyPercent,
        public int $streak,
        public bool $isMember,
    ) {
    }
}
