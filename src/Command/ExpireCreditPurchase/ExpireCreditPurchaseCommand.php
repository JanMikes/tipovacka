<?php

declare(strict_types=1);

namespace App\Command\ExpireCreditPurchase;

final readonly class ExpireCreditPurchaseCommand
{
    public function __construct(
        public string $checkoutSessionId,
    ) {
    }
}
