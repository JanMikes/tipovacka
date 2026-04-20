<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GroupCreated
{
    public function __construct(
        public Uuid $groupId,
        public Uuid $tournamentId,
        public Uuid $ownerId,
        public string $name,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
