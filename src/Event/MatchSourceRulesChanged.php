<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class MatchSourceRulesChanged
{
    public function __construct(
        public Uuid $matchSourceId,
        public Uuid $changedByUserId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
