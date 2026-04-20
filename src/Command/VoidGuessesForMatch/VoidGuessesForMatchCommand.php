<?php

declare(strict_types=1);

namespace App\Command\VoidGuessesForMatch;

use Symfony\Component\Uid\Uuid;

final readonly class VoidGuessesForMatchCommand
{
    public function __construct(
        public Uuid $sportMatchId,
    ) {
    }
}
