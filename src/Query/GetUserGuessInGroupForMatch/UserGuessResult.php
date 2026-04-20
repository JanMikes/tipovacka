<?php

declare(strict_types=1);

namespace App\Query\GetUserGuessInGroupForMatch;

use Symfony\Component\Uid\Uuid;

final readonly class UserGuessResult
{
    public function __construct(
        public Uuid $guessId,
        public int $homeScore,
        public int $awayScore,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
