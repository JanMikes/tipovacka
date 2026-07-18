<?php

declare(strict_types=1);

namespace App\Command\RegenerateCompetitionPin;

use Symfony\Component\Uid\Uuid;

final readonly class RegenerateCompetitionPinCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $competitionId,
    ) {
    }
}
