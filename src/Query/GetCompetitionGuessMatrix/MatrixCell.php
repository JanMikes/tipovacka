<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionGuessMatrix;

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
    ) {
    }

    public static function hidden(): self
    {
        return new self(homeScore: null, awayScore: null, points: null, hidden: true);
    }
}
