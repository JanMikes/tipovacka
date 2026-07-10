<?php

declare(strict_types=1);

namespace App\Command\FulfillCreditPurchase;

/**
 * Credits the wallet for a paid Stripe checkout session. Idempotent — safe to
 * dispatch from both the webhook and the checkout return page; the handler
 * verifies payment state against the Stripe API, never against request data.
 */
final readonly class FulfillCreditPurchaseCommand
{
    public function __construct(
        public string $checkoutSessionId,
    ) {
    }
}
