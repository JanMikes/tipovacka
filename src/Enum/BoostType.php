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

    /**
     * The ONE name of this booster, used by every surface without exception —
     * shop row, confirm dialog, paywall, intro modal, wizard, /cenik, the credit
     * ledger and the refund notification (item 23). There is deliberately no
     * second „marketing headline" layer any more: a booster that reads
     * differently where it is sold and where it is charged reads as two products.
     */
    public function label(): string
    {
        return match ($this) {
            self::TipDistribution => 'Jak tipují ostatní?',
            self::OthersTips => 'Přesné tipy soupeřů',
            self::TipChange => 'Počkejte si na sestavy',
        };
    }

    /**
     * The ONE sentence describing this booster. No surface may hand-write its own
     * prose about a booster; it renders this instead.
     *
     * Note on {@see self::TipChange}: the window is the competition's own
     * `tipChangeOffsetMinutes` (default 60), so a competition that moved it off
     * the default needs the offset substituted — {@see \App\Query\GetBoostPanel\GetBoostPanelResult::$tipChangeOffsetMinutes}
     * and the one branch in `Boost/Panel.html.twig` that uses it.
     */
    public function description(): string
    {
        return match ($this) {
            self::TipDistribution => 'Odemkněte procentuální rozložení tipů 1 / X / 2 ostatních hráčů ve vaší soutěži. Konkrétní tipy zůstávají skryté.',
            self::OthersTips => 'Chcete vědět, jak tipuje váš soupeř? Odemkněte si přesné tipy ostatních hráčů ve vaší soutěži.',
            self::TipChange => 'Chcete si počkat na soupisky? Odemkněte si možnost upravit své tipy až 1 hodinu před začátkem zápasu.',
        };
    }
}
