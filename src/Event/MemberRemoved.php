<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class MemberRemoved
{
    public function __construct(
        public Uuid $membershipId,
        public Uuid $groupId,
        public Uuid $userId,
        public Uuid $removedByUserId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
