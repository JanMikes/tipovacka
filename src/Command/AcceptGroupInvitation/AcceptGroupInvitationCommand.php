<?php

declare(strict_types=1);

namespace App\Command\AcceptGroupInvitation;

use Symfony\Component\Uid\Uuid;

final readonly class AcceptGroupInvitationCommand
{
    public function __construct(
        public Uuid $userId,
        public string $token,
    ) {
    }
}
