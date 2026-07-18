<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchEventType: string
{
    case Goal = 'goal';
    case YellowCard = 'yellow_card';
    case RedCard = 'red_card';
}
