<?php

declare(strict_types=1);

namespace App\Service\Competition;

final class InvitationTokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
