<?php

declare(strict_types=1);

namespace App\Query\GetBoostPanel;

use App\Enum\BoostType;
use App\Enum\CompetitionMonetization;

final readonly class GetBoostPanelResult
{
    /**
     * @param list<BoostType> $ownedTypes active (not-refunded) boosts the viewer holds
     */
    public function __construct(
        public CompetitionMonetization $monetization,
        public int $balance,
        public array $ownedTypes,
        public int $tipChangeOffsetMinutes,
        public bool $entitledToDistribution = false,
        public bool $entitledToOthersTips = false,
    ) {
    }

    public function owns(BoostType $type): bool
    {
        return in_array($type, $this->ownedTypes, true);
    }

    /** Distribution bar entitlement — OthersTips is a superset. */
    public function hasDistribution(): bool
    {
        return $this->owns(BoostType::TipDistribution) || $this->owns(BoostType::OthersTips);
    }

    /** A directly purchased TipDistribution boost (not merely via the OthersTips superset). */
    public function hasExplicitDistribution(): bool
    {
        return $this->owns(BoostType::TipDistribution);
    }

    public function hasOthersTips(): bool
    {
        return $this->owns(BoostType::OthersTips);
    }

    public function hasTipChange(): bool
    {
        return $this->owns(BoostType::TipChange);
    }

    public function canAfford(BoostType $type): bool
    {
        return $this->balance >= $type->price();
    }

    /**
     * The viewer already gets the distribution bar for FREE (manager/admin
     * auto-entitlement) without owning a boost — hide the buy offer entirely.
     * A member who OWNS the entitling boost is NOT auto-entitled here (their row
     * shows as owned/superseded instead).
     */
    public function autoEntitledToDistribution(): bool
    {
        return $this->entitledToDistribution && !$this->hasDistribution();
    }

    /** As {@see autoEntitledToDistribution} for concrete member tips. */
    public function autoEntitledToOthersTips(): bool
    {
        return $this->entitledToOthersTips && !$this->hasOthersTips();
    }
}
