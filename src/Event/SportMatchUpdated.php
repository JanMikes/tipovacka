<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class SportMatchUpdated
{
    public function __construct(
        public Uuid $sportMatchId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
