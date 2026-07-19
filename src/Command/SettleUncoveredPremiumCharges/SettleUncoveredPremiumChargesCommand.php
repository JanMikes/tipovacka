<?php

declare(strict_types=1);

namespace App\Command\SettleUncoveredPremiumCharges;

use Symfony\Component\Uid\Uuid;

/**
 * Retry a manager's Uncovered premium charges after they top up. Async-routed:
 * dispatched by {@see \App\Event\SettleUncoveredPremiumChargesOnTopUpHandler}
 * from the post-commit CreditsPurchased handler, processed by the worker in its
 * own transaction.
 */
final readonly class SettleUncoveredPremiumChargesCommand
{
    public function __construct(
        public Uuid $ownerId,
    ) {
    }
}
