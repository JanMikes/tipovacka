<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How a competition is monetized — premium XOR boosts, structurally impossible
 * to combine (single column). See .docs/DOMAIN.md §Monetization.
 *
 * - None: admin/global competitions default here (no wizard intent).
 * - Premium („Zaplatím za celou skupinu"): manager pays per player.
 * - Boosts („Nechám příspěvek na jednotlivcích"): players buy per-competition boosts.
 *
 * S08 stores the wizard's INTENT only; charging goes live in S10.
 */
enum CompetitionMonetization: string
{
    case None = 'none';
    case Premium = 'premium';
    case Boosts = 'boosts';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Bez příspěvku',
            self::Premium => 'Zaplatím za celou skupinu',
            self::Boosts => 'Nechám příspěvek na jednotlivcích',
        };
    }
}
