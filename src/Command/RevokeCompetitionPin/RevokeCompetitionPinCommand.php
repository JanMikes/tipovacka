<?php

declare(strict_types=1);

namespace App\Command\RevokeCompetitionPin;

use Symfony\Component\Uid\Uuid;

final readonly class RevokeCompetitionPinCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $competitionId,
    ) {
    }
}
