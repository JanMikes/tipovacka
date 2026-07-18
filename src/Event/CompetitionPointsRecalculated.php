<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class CompetitionPointsRecalculated
{
    public function __construct(
        public Uuid $competitionId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
