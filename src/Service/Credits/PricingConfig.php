<?php

declare(strict_types=1);

namespace App\Service\Credits;

/**
 * The single place where credit prices live (1 credit = 1 Kč).
 * Values follow the locked pricing decisions in .docs/DOMAIN.md
 * (§Monetization + decision log 2026-07-18, boost prices re-set 2026-07-30) —
 * never scatter these literals.
 */
final class PricingConfig
{
    /** Premium: manager pays per player, charged at each join. */
    public const int PREMIUM_PER_PLAYER = 10;

    /** Boost „Jak tipují ostatní?" (anonymous distribution bar). */
    public const int BOOST_TIP_DISTRIBUTION = 15;

    /** Boost „Přesné tipy soupeřů" (includes the distribution bar). */
    public const int BOOST_OTHERS_TIPS = 35;

    /** Boost „Počkejte si na sestavy". */
    public const int BOOST_TIP_CHANGE = 50;

    /** Warn a premium manager below this balance (5 players' worth). */
    public const int LOW_BALANCE_WARNING_THRESHOLD = 50;
}
