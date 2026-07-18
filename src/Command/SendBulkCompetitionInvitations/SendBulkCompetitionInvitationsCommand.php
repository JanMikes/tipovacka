<?php

declare(strict_types=1);

namespace App\Command\SendBulkCompetitionInvitations;

use Symfony\Component\Uid\Uuid;

final readonly class SendBulkCompetitionInvitationsCommand
{
    public function __construct(
        public Uuid $inviterId,
        public Uuid $competitionId,
        public string $rawEmails,
    ) {
    }
}
