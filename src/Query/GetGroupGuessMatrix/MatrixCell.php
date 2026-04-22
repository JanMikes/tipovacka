<?php

declare(strict_types=1);

namespace App\Query\GetGroupGuessMatrix;

final readonly class MatrixCell
{
    public function __construct(
        public ?int $homeScore,
        public ?int $awayScore,
        public ?int $points,
        public bool $hidden = false,
    ) {
    }

    public static function hidden(): self
    {
        return new self(homeScore: null, awayScore: null, points: null, hidden: true);
    }
}
