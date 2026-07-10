<?php

declare(strict_types=1);

namespace App\Service\Payment;

/**
 * A freshly created Stripe Checkout session — the url is the hosted payment
 * page the user is redirected to. It is ephemeral and never persisted.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
    ) {
    }
}
