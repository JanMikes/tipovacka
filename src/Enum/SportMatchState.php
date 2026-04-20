<?php

declare(strict_types=1);

namespace App\Enum;

enum SportMatchState: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Finished = 'finished';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
}
