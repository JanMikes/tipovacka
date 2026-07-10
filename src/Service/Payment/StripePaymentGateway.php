<?php

declare(strict_types=1);

namespace App\Service\Payment;

use Stripe\StripeClient;
use Symfony\Component\Uid\Uuid;

final class StripePaymentGateway implements PaymentGateway
{
    /**
     * Stable across environments (sandbox/live) — created by bin/stripe-bootstrap.sh.
     * Resolved to a price id at runtime so no environment-specific id is configured.
     */
    public const string PRICE_LOOKUP_KEY = 'wtips_credit_czk';

    public const string METADATA_APP = 'wtips';

    private readonly StripeClient $client;

    private ?string $creditPriceId = null;

    public function __construct(#[\SensitiveParameter] string $secretKey)
    {
        $this->client = new StripeClient($secretKey);
    }

    public function createCustomer(string $email, string $name, Uuid $userId): string
    {
        $customer = $this->client->customers->create([
            'email' => $email,
            'name' => $name,
            'metadata' => [
                'app' => self::METADATA_APP,
                'user_id' => $userId->toRfc4122(),
            ],
        ]);

        return $customer->id;
    }

    public function createCreditCheckoutSession(
        string $customerId,
        int $credits,
        Uuid $purchaseId,
        Uuid $userId,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $customerId,
            'line_items' => [[
                'price' => $this->resolveCreditPriceId(),
                'quantity' => $credits,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'locale' => 'cs',
            'invoice_creation' => ['enabled' => true],
            'client_reference_id' => $purchaseId->toRfc4122(),
            'metadata' => [
                'app' => self::METADATA_APP,
                'purchase_id' => $purchaseId->toRfc4122(),
                'user_id' => $userId->toRfc4122(),
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'app' => self::METADATA_APP,
                    'purchase_id' => $purchaseId->toRfc4122(),
                    'user_id' => $userId->toRfc4122(),
                ],
            ],
        ]);

        if (null === $session->url) {
            throw new \RuntimeException(sprintf('Stripe checkout session "%s" nemá URL.', $session->id));
        }

        return new CheckoutSession($session->id, $session->url);
    }

    public function getCheckoutSession(string $sessionId): CheckoutSessionDetails
    {
        $session = $this->client->checkout->sessions->retrieve($sessionId);

        /** @var array<string, string> $metadata */
        $metadata = null !== $session->metadata ? $session->metadata->toArray() : [];

        return new CheckoutSessionDetails(
            id: $session->id,
            status: (string) $session->status,
            paymentStatus: (string) $session->payment_status,
            paymentIntentId: is_string($session->payment_intent) ? $session->payment_intent : $session->payment_intent?->id,
            invoiceId: is_string($session->invoice) ? $session->invoice : $session->invoice?->id,
            amountTotal: $session->amount_total,
            currency: $session->currency,
            metadata: $metadata,
        );
    }

    public function getInvoice(string $invoiceId): InvoiceDetails
    {
        $invoice = $this->client->invoices->retrieve($invoiceId);

        return new InvoiceDetails(
            id: (string) $invoice->id,
            hostedInvoiceUrl: $invoice->hosted_invoice_url,
            invoicePdfUrl: $invoice->invoice_pdf,
        );
    }

    private function resolveCreditPriceId(): string
    {
        if (null !== $this->creditPriceId) {
            return $this->creditPriceId;
        }

        $prices = $this->client->prices->all([
            'lookup_keys' => [self::PRICE_LOOKUP_KEY],
            'active' => true,
            'limit' => 1,
        ]);

        $price = $prices->first();

        if (null === $price) {
            throw new \RuntimeException(sprintf('Stripe price s lookup key "%s" neexistuje — spusťte bin/stripe-bootstrap.sh.', self::PRICE_LOOKUP_KEY));
        }

        return $this->creditPriceId = $price->id;
    }
}
