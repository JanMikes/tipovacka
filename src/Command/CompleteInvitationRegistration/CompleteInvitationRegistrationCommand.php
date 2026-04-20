<?php

declare(strict_types=1);

namespace App\Command\CompleteInvitationRegistration;

final readonly class CompleteInvitationRegistrationCommand
{
    public function __construct(
        public string $token,
        public string $plainPassword,
    ) {
    }
}
