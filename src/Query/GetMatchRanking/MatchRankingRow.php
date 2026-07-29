<?php

declare(strict_types=1);

namespace App\Query\GetMatchRanking;

use Symfony\Component\Uid\Uuid;

final readonly class MatchRankingRow
{
    public function __construct(
        /** Null while the match is unscored — an unevaluated tip has no position. */
        public ?int $rank,
        public Uuid $userId,
        public string $nickname,
        public ?string $fullName,
        public int $guessHome,
        public int $guessAway,
        /** Null while the match is unscored (evaluations are written on finish). */
        public ?int $totalPoints,
    ) {
    }
}
