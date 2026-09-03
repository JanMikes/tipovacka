<?php

declare(strict_types=1);

namespace App\Query\GetMatchRanking;

use App\Enum\MatchSide;
use Symfony\Component\Uid\Uuid;

final readonly class MatchRankingRow
{
    /**
     * @param list<array{0: int, 1: int}>|null $periodScores the tip's per-period pairs, when the
     *                                                       soutěž enables the period rule
     * @param list<string>                     $scorerNames  the tip's guessed scorers, sorted
     */
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
        /* The optional tip parts. Item 22 folded „Jak tipovali ostatní" into this
           board, and these three lines were the only thing that surface had and this
           one did not — so they travel with the row rather than being lost. */
        public ?array $periodScores = null,
        public ?int $overtimeHomeScore = null,
        public ?int $overtimeAwayScore = null,
        public array $scorerNames = [],
        /** Who the tip says wins after extra time / penalties — the pair above is never shown as a score. */
        public ?MatchSide $overtimeWinner = null,
    ) {
    }
}
