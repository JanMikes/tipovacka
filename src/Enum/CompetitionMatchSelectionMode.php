<?php

declare(strict_types=1);

namespace App\Enum;

enum CompetitionMatchSelectionMode: string
{
    case All = 'all';
    case Subset = 'subset';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Všechny zápasy',
            self::Subset => 'Vybrané zápasy',
        };
    }
}
