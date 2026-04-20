<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class JoinRequestApproved
{
    public function __construct(
        public Uuid $requestId,
        public Uuid $membershipId,
        public Uuid $groupId,
        public Uuid $userId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
