<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchEventType: string
{
    case Goal = 'goal';
    case YellowCard = 'yellow_card';
    case RedCard = 'red_card';

    /** Timeline label („Gól — Messi (ARG)"). */
    public function label(): string
    {
        return match ($this) {
            self::Goal => 'Gól',
            self::YellowCard => 'Žlutá',
            self::RedCard => 'Červená',
        };
    }
}
