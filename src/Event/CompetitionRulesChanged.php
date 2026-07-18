<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class CompetitionRulesChanged
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $changedByUserId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
