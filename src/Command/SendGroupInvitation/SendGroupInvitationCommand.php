<?php

declare(strict_types=1);

namespace App\Command\SendGroupInvitation;

use Symfony\Component\Uid\Uuid;

final readonly class SendGroupInvitationCommand
{
    public function __construct(
        public Uuid $inviterId,
        public Uuid $groupId,
        public string $email,
    ) {
    }
}
