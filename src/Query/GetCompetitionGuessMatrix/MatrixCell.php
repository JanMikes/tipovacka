<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionGuessMatrix;

use App\Enum\MatchSide;

final readonly class MatrixCell
{
    /**
     * @param list<array{int, int}>|null $periodScores
     * @param list<string>               $scorerNames
     */
    public function __construct(
        public ?int $homeScore,
        public ?int $awayScore,
        public ?int $points,
        public bool $hidden = false,
        public ?array $periodScores = null,
        public ?int $overtimeHomeScore = null,
        public ?int $overtimeAwayScore = null,
        public array $scorerNames = [],
        /** Who the tip says wins after extra time / penalties — the pair above is never shown as a score. */
        public ?MatchSide $overtimeWinner = null,
    ) {
    }

    public static function hidden(): self
    {
        return new self(homeScore: null, awayScore: null, points: null, hidden: true);
    }
}
