<?php

declare(strict_types=1);

namespace App\Enum;

use App\Service\Credits\PricingConfig;

/**
 * Per-competition boost a player may buy when the competition is monetized as
 * `boosts`. Prices live in {@see PricingConfig} — never scatter the literals.
 *
 * Superset: {@see self::OthersTips} entitles the buyer to the distribution bar
 * too ({@see self::TipDistribution}) at the entitlement level — no separate
 * purchase, and the TipDistribution offer is hidden once OthersTips is owned.
 * See .docs/DOMAIN.md §Monetization.
 */
enum BoostType: string
{
    case TipDistribution = 'tip_distribution';
    case OthersTips = 'others_tips';
    case TipChange = 'tip_change';

    public function price(): int
    {
        return match ($this) {
            self::TipDistribution => PricingConfig::BOOST_TIP_DISTRIBUTION,
            self::OthersTips => PricingConfig::BOOST_OTHERS_TIPS,
            self::TipChange => PricingConfig::BOOST_TIP_CHANGE,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TipDistribution => 'Lišta tipů ostatních',
            self::OthersTips => 'Konkrétní tipy kolegů',
            self::TipChange => 'Měnit tip během turnaje',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TipDistribution => 'Uvidíte anonymní procenta, jak tipovali ostatní hráči (1 / X / 2).',
            self::OthersTips => 'Uvidíte konkrétní tipy soutěžících v partičce. Obsahuje i Lištu tipů ostatních.',
            self::TipChange => 'Můžete měnit svůj tip až do nastaveného času před prvním zápasem dne.',
        };
    }
}
