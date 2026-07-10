<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\CreditPurchase;
use App\Entity\CreditWallet;
use App\Entity\User;
use App\Enum\CreditTransactionType;
use App\Event\CreditsAdjustedByAdmin;
use App\Exception\InsufficientCredits;
use App\Exception\InvalidCreditAmount;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CreditWalletTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makeUser(): User
    {
        $user = new User(
            id: Uuid::v7(),
            email: 'wallet@test.com',
            password: 'h',
            nickname: 'wallet_user',
            createdAt: $this->now,
        );
        $user->popEvents();

        return $user;
    }

    private function makeWallet(User $user): CreditWallet
    {
        return new CreditWallet(
            id: Uuid::v7(),
            user: $user,
            createdAt: $this->now,
        );
    }

    private function makePendingPurchase(User $user, int $credits): CreditPurchase
    {
        return new CreditPurchase(
            id: Uuid::v7(),
            user: $user,
            credits: $credits,
            amountTotal: $credits * 100,
            currency: 'czk',
            stripeCheckoutSessionId: 'cs_test_unit',
            createdAt: $this->now,
        );
    }

    public function testNewWalletStartsAtZero(): void
    {
        $wallet = $this->makeWallet($this->makeUser());

        self::assertSame(0, $wallet->balance);
        self::assertNull($wallet->stripeCustomerId);
    }

    public function testCreditFromPurchaseIncreasesBalanceAndWritesLedger(): void
    {
        $user = $this->makeUser();
        $wallet = $this->makeWallet($user);
        $purchase = $this->makePendingPurchase($user, 250);

        $transaction = $wallet->creditFromPurchase(Uuid::v7(), $purchase, $this->now);

        self::assertSame(250, $wallet->balance);
        self::assertSame(250, $transaction->amount);
        self::assertSame(250, $transaction->balanceAfter);
        self::assertSame(CreditTransactionType::Purchase, $transaction->type);
        self::assertSame($purchase, $transaction->purchase);
        self::assertNull($transaction->note);
        self::assertNull($transaction->performedBy);
    }

    public function testConsecutiveMovementsKeepLedgerConsistent(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeUser();
        $wallet = $this->makeWallet($user);

        $first = $wallet->creditFromPurchase(Uuid::v7(), $this->makePendingPurchase($user, 100), $this->now);
        $second = $wallet->adjustByAdmin(Uuid::v7(), 50, 'Bonus', $admin, $this->now);
        $third = $wallet->adjustByAdmin(Uuid::v7(), -30, 'Korekce', $admin, $this->now);

        self::assertSame(120, $wallet->balance);
        self::assertSame(100, $first->balanceAfter);
        self::assertSame(150, $second->balanceAfter);
        self::assertSame(120, $third->balanceAfter);
    }

    public function testAdjustByAdminRecordsAuditFields(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeUser();
        $wallet = $this->makeWallet($user);

        $transaction = $wallet->adjustByAdmin(Uuid::v7(), 500, 'Výhra v soutěži', $admin, $this->now);

        self::assertSame(CreditTransactionType::AdminAdjustment, $transaction->type);
        self::assertSame('Výhra v soutěži', $transaction->note);
        self::assertSame($admin, $transaction->performedBy);

        $events = $wallet->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CreditsAdjustedByAdmin::class, $events[0]);
        self::assertSame(500, $events[0]->amount);
        self::assertSame('Výhra v soutěži', $events[0]->note);
        self::assertTrue($events[0]->adjustedById->equals($admin->id));
    }

    public function testZeroAdjustmentIsRejected(): void
    {
        $wallet = $this->makeWallet($this->makeUser());

        $this->expectException(InvalidCreditAmount::class);

        $wallet->adjustByAdmin(Uuid::v7(), 0, 'Nic', $this->makeUser(), $this->now);
    }

    public function testBalanceCanNeverGoNegative(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeUser();
        $wallet = $this->makeWallet($user);
        $wallet->adjustByAdmin(Uuid::v7(), 100, 'Init', $admin, $this->now);

        $this->expectException(InsufficientCredits::class);

        $wallet->adjustByAdmin(Uuid::v7(), -101, 'Moc velká korekce', $admin, $this->now);
    }

    public function testAssignStripeCustomerId(): void
    {
        $wallet = $this->makeWallet($this->makeUser());

        $wallet->assignStripeCustomerId('cus_test_123', $this->now);

        self::assertSame('cus_test_123', $wallet->stripeCustomerId);
    }
}
