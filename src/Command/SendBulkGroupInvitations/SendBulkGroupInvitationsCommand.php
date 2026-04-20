<?php

declare(strict_types=1);

namespace App\Command\SendBulkGroupInvitations;

use Symfony\Component\Uid\Uuid;

final readonly class SendBulkGroupInvitationsCommand
{
    public function __construct(
        public Uuid $inviterId,
        public Uuid $groupId,
        public string $rawEmails,
    ) {
    }
}
