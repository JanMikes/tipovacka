<?php

declare(strict_types=1);

namespace App\Query\GetGroupLeaderboard;

use Symfony\Component\Uid\Uuid;

final readonly class LeaderboardRow
{
    public function __construct(
        public Uuid $userId,
        public string $nickname,
        public int $totalPoints,
        public int $rank,
        public bool $isTieResolvedOverride,
    ) {
    }
}
