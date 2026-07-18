<?php

declare(strict_types=1);

namespace App\Command\SendCompetitionInvitation;

use Symfony\Component\Uid\Uuid;

final readonly class SendCompetitionInvitationCommand
{
    public function __construct(
        public Uuid $inviterId,
        public Uuid $competitionId,
        public string $email,
    ) {
    }
}
