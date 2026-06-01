<?php

declare(strict_types=1);

namespace App\Query\GetGroupLeaderboard;

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
    ) {
    }
}
