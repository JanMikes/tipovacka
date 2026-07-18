<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class CompetitionUpdated
{
    public function __construct(
        public Uuid $competitionId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
