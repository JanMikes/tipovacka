<?php

declare(strict_types=1);

namespace App\Command\PurchaseBoost;

use App\Enum\BoostType;
use Symfony\Component\Uid\Uuid;

/**
 * A player buys a per-competition boost. Requires the competition to be
 * `boosts`-monetized and the buyer to be an active member. See
 * .docs/DOMAIN.md §Monetization.
 */
final readonly class PurchaseBoostCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $competitionId,
        public BoostType $type,
    ) {
    }
}
