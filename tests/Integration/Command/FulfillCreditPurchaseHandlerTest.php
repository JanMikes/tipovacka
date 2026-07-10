<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\FulfillCreditPurchase\FulfillCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\DataFixtures\AppFixtures;
use App\Entity\CreditPurchase;
use App\Entity\CreditTransaction;
use App\Entity\CreditWallet;
use App\Enum\CreditPurchaseStatus;
use App\Enum\CreditTransactionType;
use App\Exception\CreditPurchaseMismatch;
use App\Exception\CreditPurchaseNotFound;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

final class FulfillCreditPurchaseHandlerTest extends IntegrationTestCase
{
    private function initiate(int $credits): CreditPurchase
    {
        $envelope = $this->commandBus()->dispatch(new InitiateCreditPurchaseCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            credits: $credits,
            successUrl: 'https://wtips.test/navrat?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: 'https://wtips.test/navrat?cancelled=1',
        ));

        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(InitiatedCreditCheckout::class, $result);

        return $result->purchase;
    }

    private function fulfill(string $sessionId): ?CreditPurchase
    {
        $envelope = $this->commandBus()->dispatch(new FulfillCreditPurchaseCommand($sessionId));

        /* @var CreditPurchase|null */
        return $envelope->last(HandledStamp::class)?->getResult();
    }

    private function walletBalance(): int
    {
        $wallet = $this->entityManager()->createQueryBuilder()
            ->select('w')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :userId')
            ->setParameter('userId', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->getQuery()
            ->getOneOrNullResult();

        return $wallet->balance ?? 0;
    }

    public function testPaidSessionCreditsWalletAndStoresInvoice(): void
    {
        $purchase = $this->initiate(250);
        $this->paymentGateway()->primePaidSession(
            $purchase->stripeCheckoutSessionId,
            amountTotal: 25000,
            paymentIntentId: 'pi_test_paid',
            invoiceId: 'in_test_1',
        );

        $this->fulfill($purchase->stripeCheckoutSessionId);

        $em = $this->entityManager();
        $em->clear();

        $reloaded = $em->find(CreditPurchase::class, $purchase->id);
        self::assertInstanceOf(CreditPurchase::class, $reloaded);
        self::assertSame(CreditPurchaseStatus::Completed, $reloaded->status);
        self::assertSame('pi_test_paid', $reloaded->stripePaymentIntentId);
        self::assertSame('in_test_1', $reloaded->stripeInvoiceId);
        self::assertSame('https://invoice.stripe.test/in_test_1', $reloaded->stripeInvoiceUrl);
        self::assertSame('https://invoice.stripe.test/in_test_1/pdf', $reloaded->stripeInvoicePdfUrl);
        self::assertNotNull($reloaded->completedAt);

        self::assertSame(250, $this->walletBalance());

        $transaction = $em->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.purchase = :purchaseId')
            ->setParameter('purchaseId', $purchase->id)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(CreditTransaction::class, $transaction);
        self::assertSame(250, $transaction->amount);
        self::assertSame(250, $transaction->balanceAfter);
        self::assertSame(CreditTransactionType::Purchase, $transaction->type);
    }

    public function testDuplicateWebhookDeliveryDoesNotDoubleCredit(): void
    {
        $purchase = $this->initiate(100);
        $this->paymentGateway()->primePaidSession($purchase->stripeCheckoutSessionId, amountTotal: 10000);

        $this->fulfill($purchase->stripeCheckoutSessionId);
        $this->fulfill($purchase->stripeCheckoutSessionId);
        $this->fulfill($purchase->stripeCheckoutSessionId);

        $em = $this->entityManager();
        $em->clear();

        self::assertSame(100, $this->walletBalance());

        $transactionCount = (int) $em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(CreditTransaction::class, 't')
            ->getQuery()
            ->getSingleScalarResult();

        self::assertSame(1, $transactionCount);
    }

    public function testUnpaidSessionDoesNotCredit(): void
    {
        $purchase = $this->initiate(100);

        $result = $this->fulfill($purchase->stripeCheckoutSessionId);

        self::assertInstanceOf(CreditPurchase::class, $result);
        self::assertSame(CreditPurchaseStatus::Pending, $result->status);
        self::assertSame(0, $this->walletBalance());
    }

    public function testAmountMismatchFailsLoudly(): void
    {
        $purchase = $this->initiate(100);
        // Paid amount differs from the purchase record — must never be credited silently.
        $this->paymentGateway()->primePaidSession($purchase->stripeCheckoutSessionId, amountTotal: 9900);

        try {
            $this->fulfill($purchase->stripeCheckoutSessionId);
            self::fail('Expected CreditPurchaseMismatch.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CreditPurchaseMismatch::class, $this->firstWrappedException($e));
        }
    }

    public function testUnknownSessionClaimingToBeOursFailsLoudly(): void
    {
        $this->paymentGateway()->primePaidSession('cs_test_ghost', amountTotal: 10000, metadata: ['app' => 'wtips', 'purchase_id' => 'missing']);

        try {
            $this->fulfill('cs_test_ghost');
            self::fail('Expected CreditPurchaseNotFound.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CreditPurchaseNotFound::class, $this->firstWrappedException($e));
        }
    }

    public function testForeignSessionIsIgnored(): void
    {
        $this->paymentGateway()->primePaidSession('cs_test_foreign', amountTotal: 5000, metadata: ['app' => 'other-app']);

        $result = $this->fulfill('cs_test_foreign');

        self::assertNull($result);
        self::assertSame(0, $this->walletBalance());
    }
}
