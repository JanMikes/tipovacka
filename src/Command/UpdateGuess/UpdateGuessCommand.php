<?php

declare(strict_types=1);

namespace App\Command\UpdateGuess;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateGuessCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $guessId,
        public int $homeScore,
        public int $awayScore,
    ) {
    }
}
