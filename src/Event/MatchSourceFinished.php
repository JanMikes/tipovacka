<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class MatchSourceFinished
{
    public function __construct(
        public Uuid $matchSourceId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
