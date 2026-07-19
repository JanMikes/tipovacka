<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionLeaderboard;

use Symfony\Component\Uid\Uuid;

final readonly class LeaderboardRow
{
    public function __construct(
        public Uuid $userId,
        public string $nickname,
        public ?string $fullName,
        public int $totalPoints,
        public int $rank,
        public bool $isTieResolvedOverride,
        public int $evaluatedCount = 0,
        public int $scoredCount = 0,
        public int $exactCount = 0,
        public int $partialCount = 0,
        public int $accuracyPercent = 0,
        public int $streak = 0,
        /**
         * Rank movement vs the latest snapshot day strictly before today
         * (positive = climbed). Null when there is no snapshot history at all
         * (render a neutral dot) or under a time filter (Δ is all-time only).
         */
        public ?int $delta = null,
        /** True when snapshot history exists but this member is absent from it. */
        public bool $deltaIsNew = false,
    ) {
    }
}
