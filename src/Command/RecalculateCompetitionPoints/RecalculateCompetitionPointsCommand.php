<?php

declare(strict_types=1);

namespace App\Command\RecalculateCompetitionPoints;

use Symfony\Component\Uid\Uuid;

final readonly class RecalculateCompetitionPointsCommand
{
    public function __construct(
        public Uuid $competitionId,
    ) {
    }
}
