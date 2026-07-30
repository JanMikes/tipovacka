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
    /**
     * The booster-agnostic half of the copy: what a LOCKED surface says when the
     * reason it is locked has nothing to do with which booster it is. Same rule as
     * {@see self::label()} / {@see self::description()} — no surface writes its own
     * prose, it renders these (item 23, extended 2026-07-30 to the lock overlays).
     *
     * `…_CTA` goes where a buy button would sit, `…_NOTE` where the sentence goes.
     */
    public const string LOCKED_PREMIUM_CTA = 'Zapíná organizátor';
    public const string LOCKED_PREMIUM_NOTE = 'Tuto funkci zapíná organizátor soutěže pro všechny.';
    public const string LOCKED_OVER_CTA = 'Soutěž už skončila';
    public const string LOCKED_OVER_NOTE = 'Soutěž už skončila — vylepšení už nemá co odemknout.';
    public const string LOCKED_AFTER_MATCH_CTA = 'Zobrazí se po odehrání';
    public const string LOCKED_AFTER_MATCH_NOTE = 'Zobrazí se po odehrání zápasu.';

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
     * prose about a booster; it renders this instead — including the LOCKED
     * overlays (the veiled „Jak tipují ostatní?" card and strip, the veiled
     * „Pořadí za zápas" card), which used to invent a teaser of their own.
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
