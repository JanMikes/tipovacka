<?php

declare(strict_types=1);

namespace App\Command\InitiateCreditPurchase;

use App\Entity\CreditPurchase;

final readonly class InitiatedCreditCheckout
{
    public function __construct(
        public CreditPurchase $purchase,
        public string $checkoutUrl,
    ) {
    }
}
