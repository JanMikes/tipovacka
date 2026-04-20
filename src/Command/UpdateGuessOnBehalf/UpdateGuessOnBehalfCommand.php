<?php

declare(strict_types=1);

namespace App\Command\UpdateGuessOnBehalf;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateGuessOnBehalfCommand
{
    public function __construct(
        public Uuid $actingUserId,
        public Uuid $guessId,
        public int $homeScore,
        public int $awayScore,
    ) {
    }
}
