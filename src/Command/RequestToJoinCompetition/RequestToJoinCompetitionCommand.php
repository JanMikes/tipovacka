<?php

declare(strict_types=1);

namespace App\Command\RequestToJoinCompetition;

use Symfony\Component\Uid\Uuid;

final readonly class RequestToJoinCompetitionCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $competitionId,
    ) {
    }
}
