<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GroupInvitationAccepted
{
    public function __construct(
        public Uuid $invitationId,
        public Uuid $groupId,
        public Uuid $userId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
