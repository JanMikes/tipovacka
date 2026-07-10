<?php

declare(strict_types=1);

namespace App\Command\FulfillCreditPurchase;

use App\Entity\CreditPurchase;
use App\Exception\CreditPurchaseMismatch;
use App\Exception\CreditPurchaseNotFound;
use App\Repository\CreditPurchaseRepository;
use App\Repository\CreditTransactionRepository;
use App\Service\Credits\CreditWalletProvider;
use App\Service\Identity\ProvideIdentity;
use App\Service\Payment\CheckoutSessionDetails;
use App\Service\Payment\PaymentGateway;
use App\Service\Payment\StripePaymentGateway;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FulfillCreditPurchaseHandler
{
    public function __construct(
        private CreditPurchaseRepository $purchaseRepository,
        private CreditTransactionRepository $transactionRepository,
        private CreditWalletProvider $walletProvider,
        private PaymentGateway $paymentGateway,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FulfillCreditPurchaseCommand $command): ?CreditPurchase
    {
        $purchase = $this->purchaseRepository->findByCheckoutSessionIdForUpdate($command->checkoutSessionId);

        if (null === $purchase) {
            $this->assertForeignSession($command->checkoutSessionId);

            $this->logger->info('Ignoruji Stripe session bez odpovídajícího nákupu (cizí aplikace).', [
                'sessionId' => $command->checkoutSessionId,
            ]);

            return null;
        }

        if ($purchase->isCompleted) {
            return $purchase;
        }

        if (!$purchase->isPending) {
            $this->logger->warning('Fulfillment požadavek pro nákup kreditů, který není pending.', [
                'purchaseId' => $purchase->id->toRfc4122(),
                'status' => $purchase->status->value,
            ]);

            return $purchase;
        }

        $session = $this->paymentGateway->getCheckoutSession($command->checkoutSessionId);

        if (CheckoutSessionDetails::PAYMENT_STATUS_PAID !== $session->paymentStatus) {
            $this->logger->info('Stripe session zatím není zaplacená, kredity nepřipisuji.', [
                'purchaseId' => $purchase->id->toRfc4122(),
                'paymentStatus' => $session->paymentStatus,
            ]);

            return $purchase;
        }

        if ($session->amountTotal !== $purchase->amountTotal || strtolower((string) $session->currency) !== $purchase->currency) {
            throw CreditPurchaseMismatch::forSession($purchase->id, $session->id, $session->amountTotal, $session->currency, $purchase->amountTotal, $purchase->currency);
        }

        $now = $this->clock->now();

        $purchase->markCompleted($session->paymentIntentId, $now);

        $wallet = $this->walletProvider->getForUpdateOrCreate($purchase->user, $now);
        $transaction = $wallet->creditFromPurchase($this->identity->next(), $purchase, $now);
        $this->transactionRepository->save($transaction);

        if (null !== $session->invoiceId) {
            $invoice = $this->paymentGateway->getInvoice($session->invoiceId);
            $purchase->attachInvoice($invoice->id, $invoice->hostedInvoiceUrl, $invoice->invoicePdfUrl, $now);
        }

        return $purchase;
    }

    /**
     * A session we have no purchase for: sessions of other apps on the same
     * Stripe account are ignored, but a session that claims to be ours means
     * lost data — that must fail loudly (webhook retries + Sentry).
     */
    private function assertForeignSession(string $sessionId): void
    {
        $session = $this->paymentGateway->getCheckoutSession($sessionId);

        if (StripePaymentGateway::METADATA_APP === ($session->metadata['app'] ?? null)) {
            throw CreditPurchaseNotFound::withCheckoutSessionId($sessionId);
        }
    }
}
