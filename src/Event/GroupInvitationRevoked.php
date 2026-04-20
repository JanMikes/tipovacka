<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GroupInvitationRevoked
{
    public function __construct(
        public Uuid $invitationId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
