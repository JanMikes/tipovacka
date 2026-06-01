<?php

declare(strict_types=1);

namespace App\Query\GetMatchRanking;

use Symfony\Component\Uid\Uuid;

final readonly class MatchRankingRow
{
    public function __construct(
        public int $rank,
        public Uuid $userId,
        public string $nickname,
        public ?string $fullName,
        public int $guessHome,
        public int $guessAway,
        public int $totalPoints,
    ) {
    }
}
