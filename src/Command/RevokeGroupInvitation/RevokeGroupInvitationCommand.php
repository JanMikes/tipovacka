<?php

declare(strict_types=1);

namespace App\Command\RevokeGroupInvitation;

use Symfony\Component\Uid\Uuid;

final readonly class RevokeGroupInvitationCommand
{
    public function __construct(
        public Uuid $revokerId,
        public Uuid $invitationId,
    ) {
    }
}
