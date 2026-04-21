<?php

declare(strict_types=1);

namespace App\Command\DeleteGuess;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteGuessCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $guessId,
    ) {
    }
}
