<?php

declare(strict_types=1);

namespace App\Command\LeaveCompetition;

use Symfony\Component\Uid\Uuid;

final readonly class LeaveCompetitionCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $competitionId,
    ) {
    }
}
