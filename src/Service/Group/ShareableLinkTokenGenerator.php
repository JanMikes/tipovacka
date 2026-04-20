<?php

declare(strict_types=1);

namespace App\Service\Group;

final class ShareableLinkTokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(24));
    }
}
