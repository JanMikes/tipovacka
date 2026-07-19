<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Credits\CreditsWord;
use App\Service\Credits\PricingConfig;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

/**
 * Exposes the single source of truth for credit prices ({@see PricingConfig}) to
 * templates as the `pricing` global, so marketing/Ceník copy never hardcodes a
 * price literal. 1 credit = 1 Kč. Access e.g. `{{ pricing.premiumPerPlayer }}`.
 *
 * Also provides the `|credits` filter for correct Czech pluralisation of amounts:
 * `{{ 1|credits }}` → „1 kredit", `{{ 2|credits }}` → „2 kredity",
 * `{{ 50|credits }}` → „50 kreditů".
 */
final class PricingExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('credits', CreditsWord::format(...)),
        ];
    }

    /**
     * @return array{pricing: array{premiumPerPlayer: int, boostTipDistribution: int, boostOthersTips: int, boostTipChange: int}}
     */
    public function getGlobals(): array
    {
        return [
            'pricing' => [
                'premiumPerPlayer' => PricingConfig::PREMIUM_PER_PLAYER,
                'boostTipDistribution' => PricingConfig::BOOST_TIP_DISTRIBUTION,
                'boostOthersTips' => PricingConfig::BOOST_OTHERS_TIPS,
                'boostTipChange' => PricingConfig::BOOST_TIP_CHANGE,
            ],
        ];
    }
}
