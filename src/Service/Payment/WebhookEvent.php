<?php

declare(strict_types=1);

namespace App\Service\Payment;

final readonly class WebhookEvent
{
    public function __construct(
        public string $id,
        public string $type,
        /** Present when the event's data object is a Checkout session. */
        public ?string $checkoutSessionId,
    ) {
    }
}
