<?php

declare(strict_types=1);

namespace App\Service\Payment;

use Symfony\Component\Uid\Uuid;

interface PaymentGateway
{
    /**
     * Creates a Stripe customer for the user and returns its id (cus_...).
     */
    public function createCustomer(string $email, string $name, Uuid $userId): string;

    /**
     * Creates a hosted Checkout session selling $credits × 1 CZK with
     * Stripe-issued invoice enabled.
     */
    public function createCreditCheckoutSession(
        string $customerId,
        int $credits,
        Uuid $purchaseId,
        Uuid $userId,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession;

    public function getCheckoutSession(string $sessionId): CheckoutSessionDetails;

    public function getInvoice(string $invoiceId): InvoiceDetails;
}
