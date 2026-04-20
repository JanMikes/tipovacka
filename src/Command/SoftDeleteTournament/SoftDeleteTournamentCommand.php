<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteTournament;

use Symfony\Component\Uid\Uuid;

final readonly class SoftDeleteTournamentCommand
{
    public function __construct(
        public Uuid $tournamentId,
    ) {
    }
}
