<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CreditPurchase;
use App\Entity\User;
use App\Enum\CreditPurchaseStatus;
use App\Event\CreditsPurchased;
use App\Exception\CreditPurchaseNotPending;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreditPurchaseTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makePurchase(): CreditPurchase
    {
        $user = new User(
            id: Uuid::v7(),
            email: 'buyer@test.com',
            password: 'h',
            nickname: 'buyer',
            createdAt: $this->now,
        );
        $user->popEvents();

        return new CreditPurchase(
            id: Uuid::v7(),
            user: $user,
            credits: 250,
            amountTotal: 25000,
            currency: 'czk',
            stripeCheckoutSessionId: 'cs_test_unit',
            createdAt: $this->now,
        );
    }

    public function testStartsPending(): void
    {
        $purchase = $this->makePurchase();

        self::assertSame(CreditPurchaseStatus::Pending, $purchase->status);
        self::assertTrue($purchase->isPending);
        self::assertFalse($purchase->isCompleted);
    }

    public function testMarkCompletedRecordsEventAndPaymentIntent(): void
    {
        $purchase = $this->makePurchase();

        $purchase->markCompleted('pi_test_1', $this->now);

        self::assertTrue($purchase->isCompleted);
        self::assertSame('pi_test_1', $purchase->stripePaymentIntentId);
        self::assertEquals($this->now, $purchase->completedAt);

        $events = $purchase->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CreditsPurchased::class, $events[0]);
        self::assertSame(250, $events[0]->credits);
        self::assertTrue($events[0]->purchaseId->equals($purchase->id));
    }

    public function testCannotCompleteTwice(): void
    {
        $purchase = $this->makePurchase();
        $purchase->markCompleted('pi_test_1', $this->now);

        $this->expectException(CreditPurchaseNotPending::class);

        $purchase->markCompleted('pi_test_2', $this->now);
    }

    public function testCannotExpireCompletedPurchase(): void
    {
        $purchase = $this->makePurchase();
        $purchase->markCompleted('pi_test_1', $this->now);

        $this->expectException(CreditPurchaseNotPending::class);

        $purchase->markExpired($this->now);
    }

    public function testMarkExpired(): void
    {
        $purchase = $this->makePurchase();

        $purchase->markExpired($this->now);

        self::assertSame(CreditPurchaseStatus::Expired, $purchase->status);
        self::assertCount(0, $purchase->popEvents());
    }

    public function testMarkFailed(): void
    {
        $purchase = $this->makePurchase();

        $purchase->markFailed($this->now);

        self::assertSame(CreditPurchaseStatus::Failed, $purchase->status);
    }

    public function testAttachInvoice(): void
    {
        $purchase = $this->makePurchase();

        $purchase->attachInvoice('in_test_1', 'https://invoice.stripe.test/in_test_1', 'https://invoice.stripe.test/in_test_1/pdf', $this->now);

        self::assertSame('in_test_1', $purchase->stripeInvoiceId);
        self::assertSame('https://invoice.stripe.test/in_test_1', $purchase->stripeInvoiceUrl);
        self::assertSame('https://invoice.stripe.test/in_test_1/pdf', $purchase->stripeInvoicePdfUrl);
    }
}
