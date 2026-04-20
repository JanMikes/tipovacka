<?php

declare(strict_types=1);

namespace App\Enum;

enum JoinRequestDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Schváleno',
            self::Rejected => 'Zamítnuto',
        };
    }
}
