<?php

declare(strict_types=1);

namespace App\Query\GetGroupGuessMatrix;

final readonly class MatrixCell
{
    public function __construct(
        public int $homeScore,
        public int $awayScore,
        public ?int $points,
    ) {
    }
}
