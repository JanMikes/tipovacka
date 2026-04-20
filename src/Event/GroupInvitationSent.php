<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GroupInvitationSent
{
    public function __construct(
        public Uuid $invitationId,
        public Uuid $groupId,
        public Uuid $inviterId,
        public string $email,
        public string $token,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
