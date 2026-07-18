<?php

declare(strict_types=1);

namespace App\Command\AcceptCompetitionInvitation;

use Symfony\Component\Uid\Uuid;

final readonly class AcceptCompetitionInvitationCommand
{
    public function __construct(
        public Uuid $userId,
        public string $token,
    ) {
    }
}
