<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\MatchSourceKind;
use Symfony\Component\Uid\Uuid;

final readonly class MatchSourceCreated
{
    public function __construct(
        public Uuid $matchSourceId,
        public Uuid $ownerId,
        public MatchSourceKind $kind,
        public string $name,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
