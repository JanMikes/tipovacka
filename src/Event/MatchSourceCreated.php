<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\MatchSourceVisibility;
use Symfony\Component\Uid\Uuid;

final readonly class MatchSourceCreated
{
    public function __construct(
        public Uuid $matchSourceId,
        public Uuid $ownerId,
        public MatchSourceVisibility $visibility,
        public string $name,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
