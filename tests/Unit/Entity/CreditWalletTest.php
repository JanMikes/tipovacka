<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Competition;
use App\Entity\CreditPurchase;
use App\Entity\CreditWallet;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\CreditTransactionType;
use App\Enum\MatchSourceKind;
use App\Event\CreditsAdjustedByAdmin;
use App\Event\CreditsRefunded;
use App\Event\CreditsSpent;
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

    private function makeCompetition(User $owner): Competition
    {
        $matchSource = new MatchSource(
            id: Uuid::v7(),
            sport: new Sport(Uuid::v7(), 'football', 'Fotbal'),
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'Turnaj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $matchSource->popEvents();

        $competition = new Competition(
            id: Uuid::v7(),
            matchSource: $matchSource,
            owner: $owner,
            name: 'Kancelářská tipovačka',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $competition->popEvents();

        return $competition;
    }

    private function fundedWallet(User $user, int $balance): CreditWallet
    {
        $wallet = $this->makeWallet($user);
        $wallet->adjustByAdmin(Uuid::v7(), $balance, 'Init', $this->makeUser(), $this->now);
        $wallet->popEvents();

        return $wallet;
    }

    public function testSpendWritesLedgerWithReferencesAndRecordsEvent(): void
    {
        $user = $this->makeUser();
        $relatedUser = $this->makeUser();
        $wallet = $this->fundedWallet($user, 100);
        $competition = $this->makeCompetition($user);

        $transaction = $wallet->spend(
            transactionId: Uuid::v7(),
            amount: 30,
            type: CreditTransactionType::BoostPurchase,
            now: $this->now,
            competition: $competition,
            relatedUser: $relatedUser,
            boostType: 'tip_change',
            note: 'Vylepšení',
        );

        self::assertSame(70, $wallet->balance);
        self::assertSame(-30, $transaction->amount);
        self::assertSame(70, $transaction->balanceAfter);
        self::assertSame(CreditTransactionType::BoostPurchase, $transaction->type);
        self::assertSame($competition, $transaction->competition);
        self::assertSame($relatedUser, $transaction->relatedUser);
        self::assertSame('tip_change', $transaction->boostType);
        self::assertSame('Vylepšení', $transaction->note);
        self::assertNull($transaction->performedBy);
        self::assertNull($transaction->purchase);

        $events = $wallet->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CreditsSpent::class, $events[0]);
        self::assertTrue($events[0]->walletUserId->equals($user->id));
        self::assertSame(30, $events[0]->amount);
        self::assertSame(CreditTransactionType::BoostPurchase, $events[0]->type);
        self::assertTrue($events[0]->competitionId?->equals($competition->id));
        self::assertTrue($events[0]->relatedUserId?->equals($relatedUser->id));
        self::assertSame('tip_change', $events[0]->boostType);
        self::assertSame(70, $events[0]->balanceAfter);
        self::assertEquals($this->now, $events[0]->occurredOn);
    }

    public function testSpendWithoutReferencesLeavesThemNull(): void
    {
        $wallet = $this->fundedWallet($this->makeUser(), 100);

        $transaction = $wallet->spend(Uuid::v7(), 100, CreditTransactionType::EntryFee, $this->now);

        self::assertSame(0, $wallet->balance);
        self::assertNull($transaction->competition);
        self::assertNull($transaction->relatedUser);
        self::assertNull($transaction->boostType);
        self::assertNull($transaction->note);

        $events = $wallet->popEvents();
        self::assertInstanceOf(CreditsSpent::class, $events[0]);
        self::assertNull($events[0]->competitionId);
        self::assertNull($events[0]->relatedUserId);
        self::assertNull($events[0]->boostType);
    }

    public function testInsufficientSpendThrowsAndLeavesBalanceUntouched(): void
    {
        $wallet = $this->fundedWallet($this->makeUser(), 100);

        try {
            $wallet->spend(Uuid::v7(), 130, CreditTransactionType::EntryFee, $this->now);
            self::fail('Expected InsufficientCredits.');
        } catch (InsufficientCredits $e) {
            self::assertStringContainsString('chybí 30', $e->getMessage());
        }

        self::assertSame(100, $wallet->balance);
        self::assertSame([], $wallet->popEvents());
    }

    public function testSpendRejectsZeroAmount(): void
    {
        $wallet = $this->fundedWallet($this->makeUser(), 100);

        $this->expectException(InvalidCreditAmount::class);

        $wallet->spend(Uuid::v7(), 0, CreditTransactionType::EntryFee, $this->now);
    }

    public function testSpendRejectsNegativeAmount(): void
    {
        $wallet = $this->fundedWallet($this->makeUser(), 100);

        $this->expectException(InvalidCreditAmount::class);

        $wallet->spend(Uuid::v7(), -5, CreditTransactionType::EntryFee, $this->now);
    }

    public function testSpendRejectsNonSpendType(): void
    {
        $wallet = $this->fundedWallet($this->makeUser(), 100);

        $this->expectException(\LogicException::class);

        $wallet->spend(Uuid::v7(), 10, CreditTransactionType::PremiumRefund, $this->now);
    }

    public function testRefundRejectsNonRefundType(): void
    {
        $wallet = $this->fundedWallet($this->makeUser(), 100);

        $this->expectException(\LogicException::class);

        $wallet->refund(Uuid::v7(), 10, CreditTransactionType::PremiumCharge, $this->now);
    }

    public function testRefundRejectsNonPositiveAmount(): void
    {
        $wallet = $this->fundedWallet($this->makeUser(), 100);

        $this->expectException(InvalidCreditAmount::class);

        $wallet->refund(Uuid::v7(), 0, CreditTransactionType::BoostRefund, $this->now);
    }

    public function testRefundCreditsBackAndRecordsEvent(): void
    {
        $user = $this->makeUser();
        $relatedUser = $this->makeUser();
        $wallet = $this->fundedWallet($user, 100);
        $competition = $this->makeCompetition($user);

        $wallet->spend(Uuid::v7(), 40, CreditTransactionType::PremiumCharge, $this->now, $competition, $relatedUser);
        $wallet->popEvents();

        $transaction = $wallet->refund(
            transactionId: Uuid::v7(),
            amount: 40,
            refundType: CreditTransactionType::PremiumRefund,
            now: $this->now,
            competition: $competition,
            relatedUser: $relatedUser,
        );

        self::assertSame(100, $wallet->balance);
        self::assertSame(40, $transaction->amount);
        self::assertSame(100, $transaction->balanceAfter);
        self::assertSame(CreditTransactionType::PremiumRefund, $transaction->type);
        self::assertSame($competition, $transaction->competition);
        self::assertSame($relatedUser, $transaction->relatedUser);
        self::assertNull($transaction->performedBy);

        $events = $wallet->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CreditsRefunded::class, $events[0]);
        self::assertTrue($events[0]->walletUserId->equals($user->id));
        self::assertSame(40, $events[0]->amount);
        self::assertSame(CreditTransactionType::PremiumRefund, $events[0]->type);
        self::assertTrue($events[0]->competitionId?->equals($competition->id));
        self::assertTrue($events[0]->relatedUserId?->equals($relatedUser->id));
        self::assertNull($events[0]->boostType);
        self::assertSame(100, $events[0]->balanceAfter);
    }
}
