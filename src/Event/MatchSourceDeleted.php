<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class MatchSourceDeleted
{
    public function __construct(
        public Uuid $matchSourceId,
        public Uuid $ownerId,
        public string $name,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
