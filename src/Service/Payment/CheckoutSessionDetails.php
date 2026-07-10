<?php

declare(strict_types=1);

namespace App\Service\Payment;

/**
 * Snapshot of a Stripe Checkout session fetched from the API — the source of
 * truth for fulfillment decisions (never trust request/webhook payloads alone).
 */
final readonly class CheckoutSessionDetails
{
    public const string PAYMENT_STATUS_PAID = 'paid';

    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $paymentStatus,
        public ?string $paymentIntentId,
        public ?string $invoiceId,
        public ?int $amountTotal,
        public ?string $currency,
        public array $metadata,
    ) {
    }
}
