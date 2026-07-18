<?php

declare(strict_types=1);

namespace App\Command\RevokeCompetitionInvitation;

use Symfony\Component\Uid\Uuid;

final readonly class RevokeCompetitionInvitationCommand
{
    public function __construct(
        public Uuid $revokerId,
        public Uuid $invitationId,
    ) {
    }
}
