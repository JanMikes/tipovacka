<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionMonetizationOverview;

use App\Enum\CompetitionMonetization;

final readonly class CompetitionMonetizationOverviewResult
{
    /**
     * @param list<PremiumChargeRow> $premiumCharges
     * @param list<ActiveBoostRow>   $activeBoosts
     */
    public function __construct(
        public CompetitionMonetization $monetization,
        public array $premiumCharges,
        public int $chargedCount,
        public int $uncoveredCount,
        public int $chargedCredits,
        public array $activeBoosts,
        public int $boostCredits,
    ) {
    }
}
