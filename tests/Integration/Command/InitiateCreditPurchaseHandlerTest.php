<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\DataFixtures\AppFixtures;
use App\Entity\CreditPurchase;
use App\Entity\CreditWallet;
use App\Enum\CreditPurchaseStatus;
use App\Exception\InvalidCreditAmount;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

final class InitiateCreditPurchaseHandlerTest extends IntegrationTestCase
{
    private function initiate(int $credits): InitiatedCreditCheckout
    {
        $envelope = $this->commandBus()->dispatch(new InitiateCreditPurchaseCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            credits: $credits,
            successUrl: 'https://wtips.test/navrat?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: 'https://wtips.test/navrat?cancelled=1',
        ));

        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(InitiatedCreditCheckout::class, $result);

        return $result;
    }

    public function testCreatesPendingPurchaseAndCheckoutSession(): void
    {
        $checkout = $this->initiate(250);

        self::assertSame('https://checkout.stripe.test/pay/'.$checkout->purchase->stripeCheckoutSessionId, $checkout->checkoutUrl);

        $em = $this->entityManager();
        $em->clear();

        $purchase = $em->find(CreditPurchase::class, $checkout->purchase->id);
        self::assertInstanceOf(CreditPurchase::class, $purchase);
        self::assertSame(CreditPurchaseStatus::Pending, $purchase->status);
        self::assertSame(250, $purchase->credits);
        self::assertSame(25000, $purchase->amountTotal);
        self::assertSame('czk', $purchase->currency);

        // Checkout session created with the right parameters
        self::assertCount(1, $this->paymentGateway()->createdSessions);
        $session = $this->paymentGateway()->createdSessions[0];
        self::assertSame(250, $session['credits']);
        self::assertSame($purchase->id->toRfc4122(), $session['purchaseId']);
        self::assertStringContainsString('{CHECKOUT_SESSION_ID}', $session['successUrl']);

        // Stripe customer created and remembered on the wallet
        self::assertCount(1, $this->paymentGateway()->createdCustomers);
        self::assertSame(AppFixtures::VERIFIED_USER_EMAIL, $this->paymentGateway()->createdCustomers[0]['email']);

        $wallet = $em->createQueryBuilder()
            ->select('w')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :userId')
            ->setParameter('userId', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(CreditWallet::class, $wallet);
        self::assertSame('cus_test_1', $wallet->stripeCustomerId);
        self::assertSame(0, $wallet->balance);
    }

    public function testSecondPurchaseReusesStripeCustomer(): void
    {
        $this->initiate(100);
        $this->initiate(500);

        self::assertCount(1, $this->paymentGateway()->createdCustomers);
        self::assertCount(2, $this->paymentGateway()->createdSessions);
    }

    public function testRejectsPurchaseBelowMinimum(): void
    {
        try {
            $this->initiate(99);
            self::fail('Expected InvalidCreditAmount.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvalidCreditAmount::class, $this->firstWrappedException($e));
        }

        self::assertCount(0, $this->paymentGateway()->createdSessions);
    }

    public function testRejectsPurchaseAboveMaximum(): void
    {
        try {
            $this->initiate(100001);
            self::fail('Expected InvalidCreditAmount.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvalidCreditAmount::class, $this->firstWrappedException($e));
        }
    }
}
