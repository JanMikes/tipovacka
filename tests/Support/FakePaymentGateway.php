<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Payment\CheckoutSession;
use App\Service\Payment\CheckoutSessionDetails;
use App\Service\Payment\InvoiceDetails;
use App\Service\Payment\PaymentGateway;
use App\Service\Payment\StripePaymentGateway;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * In-memory PaymentGateway for tests. Created sessions start unpaid;
 * use primePaidSession() to simulate a customer completing the payment.
 */
final class FakePaymentGateway implements PaymentGateway, ResetInterface
{
    /** @var list<array{email: string, name: string, userId: string}> */
    public array $createdCustomers = [];

    /** @var list<array{customerId: string, credits: int, purchaseId: string, userId: string, successUrl: string, cancelUrl: string}> */
    public array $createdSessions = [];

    /** @var array<string, CheckoutSessionDetails> */
    public array $sessions = [];

    /** @var array<string, InvoiceDetails> */
    public array $invoices = [];

    private int $sequence = 0;

    public function createCustomer(string $email, string $name, Uuid $userId): string
    {
        $this->createdCustomers[] = ['email' => $email, 'name' => $name, 'userId' => $userId->toRfc4122()];

        return 'cus_test_'.++$this->sequence;
    }

    public function createCreditCheckoutSession(
        string $customerId,
        int $credits,
        Uuid $purchaseId,
        Uuid $userId,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $sessionId = 'cs_test_'.++$this->sequence;

        $this->createdSessions[] = [
            'customerId' => $customerId,
            'credits' => $credits,
            'purchaseId' => $purchaseId->toRfc4122(),
            'userId' => $userId->toRfc4122(),
            'successUrl' => $successUrl,
            'cancelUrl' => $cancelUrl,
        ];

        $this->sessions[$sessionId] = new CheckoutSessionDetails(
            id: $sessionId,
            status: 'open',
            paymentStatus: 'unpaid',
            paymentIntentId: null,
            invoiceId: null,
            amountTotal: $credits * 100,
            currency: 'czk',
            metadata: [
                'app' => StripePaymentGateway::METADATA_APP,
                'purchase_id' => $purchaseId->toRfc4122(),
                'user_id' => $userId->toRfc4122(),
            ],
        );

        return new CheckoutSession($sessionId, 'https://checkout.stripe.test/pay/'.$sessionId);
    }

    public function getCheckoutSession(string $sessionId): CheckoutSessionDetails
    {
        return $this->sessions[$sessionId]
            ?? throw new \RuntimeException(sprintf('FakePaymentGateway: session "%s" není naprimovaná.', $sessionId));
    }

    public function getInvoice(string $invoiceId): InvoiceDetails
    {
        return $this->invoices[$invoiceId]
            ?? throw new \RuntimeException(sprintf('FakePaymentGateway: faktura "%s" není naprimovaná.', $invoiceId));
    }

    /**
     * Simulates the customer paying an existing session (and Stripe issuing
     * the invoice), or primes a paid session that was never created through
     * this fake (e.g. fixture data).
     *
     * @param array<string, string> $metadata
     */
    public function primePaidSession(
        string $sessionId,
        int $amountTotal,
        string $currency = 'czk',
        ?string $paymentIntentId = null,
        ?string $invoiceId = null,
        array $metadata = [],
    ): void {
        $existing = $this->sessions[$sessionId] ?? null;

        $this->sessions[$sessionId] = new CheckoutSessionDetails(
            id: $sessionId,
            status: 'complete',
            paymentStatus: CheckoutSessionDetails::PAYMENT_STATUS_PAID,
            paymentIntentId: $paymentIntentId ?? 'pi_test_'.++$this->sequence,
            invoiceId: $invoiceId,
            amountTotal: $amountTotal,
            currency: $currency,
            metadata: [] !== $metadata ? $metadata : ($existing->metadata ?? []),
        );

        if (null !== $invoiceId) {
            $this->invoices[$invoiceId] = new InvoiceDetails(
                id: $invoiceId,
                hostedInvoiceUrl: 'https://invoice.stripe.test/'.$invoiceId,
                invoicePdfUrl: 'https://invoice.stripe.test/'.$invoiceId.'/pdf',
            );
        }
    }

    public function reset(): void
    {
        $this->createdCustomers = [];
        $this->createdSessions = [];
        $this->sessions = [];
        $this->invoices = [];
        $this->sequence = 0;
    }
}
