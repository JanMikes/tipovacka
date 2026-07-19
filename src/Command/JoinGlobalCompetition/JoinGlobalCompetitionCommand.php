<?php

declare(strict_types=1);

namespace App\Command\JoinGlobalCompetition;

use Symfony\Component\Uid\Uuid;

final readonly class JoinGlobalCompetitionCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $competitionId,
    ) {
    }
}
