<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class MatchSourceCompleted
{
    public function __construct(
        public Uuid $matchSourceId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
