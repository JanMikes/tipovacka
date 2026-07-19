<?php

declare(strict_types=1);

namespace App\Command\SwitchToBoosts;

use Symfony\Component\Uid\Uuid;

/**
 * Manager/admin switches a premium competition to boosts, refunding every
 * charged premium row. See .docs/DOMAIN.md §Monetization.
 */
final readonly class SwitchToBoostsCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
    ) {
    }
}
