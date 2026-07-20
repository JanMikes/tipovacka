<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\Command\CreateCuratedMatchSource\CreateCuratedMatchSourceCommand;
use App\Command\CreateGlobalCompetition\CreateGlobalCompetitionCommand;
use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\Command\FulfillCreditPurchase\FulfillCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\Command\JoinCompetitionByPin\JoinCompetitionByPinCommand;
use App\Command\JoinGlobalCompetition\JoinGlobalCompetitionCommand;
use App\Command\PurchaseBoost\PurchaseBoostCommand;
use App\Command\ReconcilePremiumCompetitions\ReconcilePremiumCompetitionsCommand;
use App\Command\SetNotificationPreference\SetNotificationPreferenceCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\CreditTransaction;
use App\Entity\CreditWallet;
use App\Entity\MatchSource;
use App\Entity\Notification;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Enum\BoostType;
use App\Enum\CompetitionMonetization;
use App\Enum\CreditTransactionType;
use App\Enum\NotificationType;
use App\Enum\PremiumChargeStatus;
use App\Event\PremiumDowngraded;
use App\Service\Competition\CompetitionEntitlements;
use App\Service\Competition\TipVisibilityGate;
use App\Service\Credits\PricingConfig;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * E2E JOURNEY 2 — global competitions & the credit economy, whole chain via the
 * command/query buses.
 *
 * Part A: admin builds a curated football source + a global competition with a
 * 50-credit entry fee and boosts monetization; a user buys credits (fake gateway),
 * pays the entry fee, buys the „Konkrétní tipy kolegů" boost, and is then entitled
 * to see others' concrete tips. Asserts the exact ledger (purchase → entry_fee →
 * boost_purchase) and balances.
 *
 * Part B: a user's premium competition — two members join, the underfunded owner
 * covers one charge and leaves the other Uncovered; reconciliation at the first
 * kickoff refunds the covered charge and downgrades to boosts. Asserts the exact
 * ledger (admin_adjustment → premium_charge → premium_refund), charge statuses,
 * and the woven premium notifications + a per-type in-app preference.
 */
final class GlobalCommerceJourneyTest extends IntegrationTestCase
{
    public function testGlobalEntryFeeBuyCreditsAndBoostEntitlement(): void
    {
        $em = $this->entityManager();
        $bus = $this->commandBus();

        $adminId = Uuid::fromString(AppFixtures::ADMIN_ID);
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        // 1) Admin creates a curated football source and gives it a match.
        $sourceId = $this->handled($bus->dispatch(new CreateCuratedMatchSourceCommand(
            adminId: $adminId,
            sportId: Uuid::fromString(Sport::FOOTBALL_ID),
            name: 'Evropská liga 2026',
            description: null,
            startAt: null,
            endAt: null,
        )), MatchSource::class)->id;

        $matchId = $this->handled($bus->dispatch(new CreateSportMatchCommand(
            matchSourceId: $sourceId,
            editorId: $adminId,
            homeTeam: 'Sparta',
            awayTeam: 'Slavia',
            kickoffAt: new \DateTimeImmutable('2025-06-25 20:00', new \DateTimeZone('UTC')),
            venue: null,
        )), SportMatch::class)->id;

        // 2) Admin creates a global competition: 50-credit entry fee, boosts.
        $competitionId = $this->handled($bus->dispatch(new CreateGlobalCompetitionCommand(
            adminId: $adminId,
            matchSourceId: $sourceId,
            name: 'Globální liga s poplatkem',
            entryFeeCredits: 50,
            monetization: CompetitionMonetization::Boosts,
        )), Competition::class)->id;

        // 3) The user buys 100 credits through the (fake) Stripe checkout.
        $checkout = $bus->dispatch(new InitiateCreditPurchaseCommand(
            userId: $userId,
            credits: 100,
            successUrl: 'https://wtips.test/navrat?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: 'https://wtips.test/navrat?cancelled=1',
        ))->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(InitiatedCreditCheckout::class, $checkout);

        $this->paymentGateway()->primePaidSession(
            $checkout->purchase->stripeCheckoutSessionId,
            amountTotal: 100 * 100,
            invoiceId: 'in_test_journey2',
        );
        $bus->dispatch(new FulfillCreditPurchaseCommand($checkout->purchase->stripeCheckoutSessionId));

        self::assertSame(100, $this->balanceOf($userId));

        // 4) The user pays the entry fee (burned) and joins the global competition.
        $bus->dispatch(new JoinGlobalCompetitionCommand(userId: $userId, competitionId: $competitionId));
        self::assertSame(100 - 50, $this->balanceOf($userId));

        // Not yet entitled to others' concrete tips.
        $entitlements = self::getContainer()->get(CompetitionEntitlements::class);
        self::assertFalse($entitlements->isEntitledToOthersTips($this->competition($competitionId), $this->user($userId)));

        // 5) The user buys the OthersTips boost (20; superset — also the bar).
        $bus->dispatch(new PurchaseBoostCommand(userId: $userId, competitionId: $competitionId, type: BoostType::OthersTips));
        self::assertSame(100 - 50 - PricingConfig::BOOST_OTHERS_TIPS, $this->balanceOf($userId));

        // 6) Now entitled — and the visibility gate lets them see others' tips
        // even before the (future) deadline.
        $entitlements->forget($competitionId);
        $em->clear();
        self::assertTrue($entitlements->isEntitledToOthersTips($this->competition($competitionId), $this->user($userId)));

        $gate = self::getContainer()->get(TipVisibilityGate::class);
        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertTrue($gate->canSeeOthersTips($this->competition($competitionId), $this->user($userId), $match));

        // 7) Exact ledger for the user: purchase → entry_fee → boost_purchase.
        $ledger = $this->transactionsOf($userId);
        self::assertCount(3, $ledger);
        self::assertSame(CreditTransactionType::Purchase, $ledger[0]->type);
        self::assertSame(100, $ledger[0]->amount);
        self::assertSame(100, $ledger[0]->balanceAfter);
        self::assertSame(CreditTransactionType::EntryFee, $ledger[1]->type);
        self::assertSame(-50, $ledger[1]->amount);
        self::assertSame(50, $ledger[1]->balanceAfter);
        self::assertSame(CreditTransactionType::BoostPurchase, $ledger[2]->type);
        self::assertSame(-20, $ledger[2]->amount);
        self::assertSame(30, $ledger[2]->balanceAfter);
    }

    public function testUserPremiumUnderfundedDowngradesRefundsAndNotifies(): void
    {
        $bus = $this->commandBus();

        $ownerId = Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID);
        $memberOneId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);
        $memberTwoId = Uuid::fromString(AppFixtures::ADMIN_ID);

        // The owner mutes the low-balance warning in-app (email stays on).
        $bus->dispatch(new SetNotificationPreferenceCommand(
            userId: $ownerId,
            type: NotificationType::PremiumBalanceLow,
            inApp: false,
            email: true,
        ));

        // 1) A from-scratch premium football competition (PIN), owner-only ⇒ no charge yet.
        $competitionId = $this->handled($bus->dispatch(new CreateCompetitionCommand(
            ownerId: $ownerId,
            name: 'Firemní prémiová liga',
            matchSourceId: null,
            sportId: Uuid::fromString(Sport::FOOTBALL_ID),
            fromScratch: true,
            withPin: true,
            monetization: CompetitionMonetization::Premium,
        )), Competition::class)->id;

        $this->entityManager()->clear();
        $competition = $this->competition($competitionId);
        $pin = $competition->pin;
        self::assertNotNull($pin);

        // A match with a future kickoff defines the reconciliation moment.
        $bus->dispatch(new CreateSportMatchCommand(
            matchSourceId: $competition->matchSource->id,
            editorId: $ownerId,
            homeTeam: 'Domácí',
            awayTeam: 'Hosté',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00', new \DateTimeZone('UTC')),
            venue: null,
        ));

        // 2) Owner is funded for exactly ONE member (10). Two members join: the
        // first is covered (Charged, 10→0), the second is Uncovered.
        $bus->dispatch(new AdjustUserCreditsCommand(
            userId: $ownerId,
            amount: PricingConfig::PREMIUM_PER_PLAYER,
            note: 'Kredity na prémium',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
        $bus->dispatch(new JoinCompetitionByPinCommand(userId: $memberOneId, pin: $pin));
        $bus->dispatch(new JoinCompetitionByPinCommand(userId: $memberTwoId, pin: $pin));

        self::assertSame(0, $this->balanceOf($ownerId));
        self::assertSame(PremiumChargeStatus::Charged, $this->chargeStatus($competitionId, $memberOneId));
        self::assertSame(PremiumChargeStatus::Uncovered, $this->chargeStatus($competitionId, $memberTwoId));

        // The owner was notified of the uncovered charge (in-app) but the low-balance
        // warning honoured the muted-in-app preference (email-only row).
        self::assertNotEmpty($this->notificationsFor($ownerId, NotificationType::PremiumChargeUncovered));
        $lowBalance = $this->notificationsFor($ownerId, NotificationType::PremiumBalanceLow);
        self::assertNotEmpty($lowBalance);
        foreach ($lowBalance as $row) {
            self::assertFalse($row->inAppVisible, 'Muted in-app ⇒ email-only row.');
        }

        // 3) The competition starts (first kickoff passes); reconciliation runs.
        $this->mockClock()->modify('+10 days');
        $this->recordedDomainEvents()->reset();
        $bus->dispatch(new ReconcilePremiumCompetitionsCommand());

        // 4) Any uncovered ⇒ every Charged row refunded, competition downgraded.
        $competition = $this->competition($competitionId);
        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
        self::assertNotNull($competition->premiumReconciledAt);
        self::assertCount(1, $this->recordedDomainEvents()->ofType(PremiumDowngraded::class));

        // Owner made whole (10 back); covered charge Refunded, uncovered stays Uncovered.
        self::assertSame(PricingConfig::PREMIUM_PER_PLAYER, $this->balanceOf($ownerId));
        self::assertSame(PremiumChargeStatus::Refunded, $this->chargeStatus($competitionId, $memberOneId));
        self::assertSame(PremiumChargeStatus::Uncovered, $this->chargeStatus($competitionId, $memberTwoId));
        self::assertNotEmpty($this->notificationsFor($ownerId, NotificationType::PremiumDowngraded));

        // 5) Exact ledger for the owner: adjustment(+10) → charge(-10) → refund(+10).
        $ledger = $this->transactionsOf($ownerId);
        self::assertCount(3, $ledger);
        self::assertSame(CreditTransactionType::AdminAdjustment, $ledger[0]->type);
        self::assertSame(10, $ledger[0]->amount);
        self::assertSame(CreditTransactionType::PremiumCharge, $ledger[1]->type);
        self::assertSame(-10, $ledger[1]->amount);
        self::assertSame(0, $ledger[1]->balanceAfter);
        self::assertSame(CreditTransactionType::PremiumRefund, $ledger[2]->type);
        self::assertSame(10, $ledger[2]->amount);
        self::assertSame(10, $ledger[2]->balanceAfter);
    }

    private function balanceOf(Uuid $userId): int
    {
        $wallet = $this->entityManager()->createQueryBuilder()
            ->select('w')->from(CreditWallet::class, 'w')
            ->where('w.user = :u')->setParameter('u', $userId)
            ->getQuery()->getOneOrNullResult();

        return $wallet instanceof CreditWallet ? $wallet->balance : 0;
    }

    /**
     * @return list<CreditTransaction> oldest first
     */
    private function transactionsOf(Uuid $userId): array
    {
        $this->entityManager()->clear();

        /** @var list<CreditTransaction> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('t')->from(CreditTransaction::class, 't')
            ->join('t.wallet', 'w')
            ->where('w.user = :u')->setParameter('u', $userId)
            ->orderBy('t.createdAt', 'ASC')->addOrderBy('t.id', 'ASC')
            ->getQuery()->getResult();

        return $rows;
    }

    private function chargeStatus(Uuid $competitionId, Uuid $memberId): PremiumChargeStatus
    {
        $charge = $this->entityManager()->createQueryBuilder()
            ->select('c')->from(CompetitionPremiumCharge::class, 'c')
            ->where('c.competition = :c AND c.member = :m')
            ->setParameter('c', $competitionId)
            ->setParameter('m', $memberId)
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(CompetitionPremiumCharge::class, $charge);

        return $charge->status;
    }

    /**
     * @return list<Notification>
     */
    private function notificationsFor(Uuid $userId, NotificationType $type): array
    {
        /** @var list<Notification> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('n')->from(Notification::class, 'n')
            ->where('n.user = :u AND n.type = :t')
            ->setParameter('u', $userId)
            ->setParameter('t', $type)
            ->getQuery()->getResult();

        return $rows;
    }

    private function competition(Uuid $competitionId): Competition
    {
        $competition = $this->entityManager()->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function user(Uuid $userId): \App\Entity\User
    {
        $user = $this->entityManager()->find(\App\Entity\User::class, $userId);
        self::assertInstanceOf(\App\Entity\User::class, $user);

        return $user;
    }

    private function mockClock(): MockClock
    {
        $clock = $this->clock();
        self::assertInstanceOf(MockClock::class, $clock);

        return $clock;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $expectedClass
     *
     * @return T
     */
    private function handled(Envelope $envelope, string $expectedClass): object
    {
        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf($expectedClass, $result);

        return $result;
    }
}
