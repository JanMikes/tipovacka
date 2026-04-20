<?php

declare(strict_types=1);

namespace App\Command\SubmitGuessOnBehalf;

use Symfony\Component\Uid\Uuid;

final readonly class SubmitGuessOnBehalfCommand
{
    public function __construct(
        public Uuid $actingUserId,
        public Uuid $targetUserId,
        public Uuid $groupId,
        public Uuid $sportMatchId,
        public int $homeScore,
        public int $awayScore,
    ) {
    }
}
