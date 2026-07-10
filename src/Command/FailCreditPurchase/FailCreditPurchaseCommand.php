<?php

declare(strict_types=1);

namespace App\Command\FailCreditPurchase;

final readonly class FailCreditPurchaseCommand
{
    public function __construct(
        public string $checkoutSessionId,
    ) {
    }
}
