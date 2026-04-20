<?php

declare(strict_types=1);

namespace App\Command\SubmitGuess;

use Symfony\Component\Uid\Uuid;

final readonly class SubmitGuessCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $groupId,
        public Uuid $sportMatchId,
        public int $homeScore,
        public int $awayScore,
    ) {
    }
}
