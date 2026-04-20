<?php

declare(strict_types=1);

namespace App\Command\RecalculateTournamentPoints;

use Symfony\Component\Uid\Uuid;

final readonly class RecalculateTournamentPointsCommand
{
    public function __construct(
        public Uuid $tournamentId,
    ) {
    }
}
