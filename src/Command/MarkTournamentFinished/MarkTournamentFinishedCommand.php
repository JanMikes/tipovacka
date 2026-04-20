<?php

declare(strict_types=1);

namespace App\Command\MarkTournamentFinished;

use Symfony\Component\Uid\Uuid;

final readonly class MarkTournamentFinishedCommand
{
    public function __construct(
        public Uuid $tournamentId,
    ) {
    }
}
