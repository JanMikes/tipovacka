<?php

declare(strict_types=1);

namespace App\Enum;

enum InvitationKind: string
{
    case Email = 'email';
    case ShareableLink = 'shareable_link';
}
