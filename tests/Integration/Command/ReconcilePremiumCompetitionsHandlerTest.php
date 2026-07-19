<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\EnablePremium\EnablePremiumCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\ReconcilePremiumCompetitions\ReconcilePremiumCompetitionsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\CreditTransaction;
use App\Entity\CreditWallet;
use App\Enum\CompetitionMonetization;
use App\Enum\CreditTransactionType;
use App\Enum\PremiumChargeStatus;
use App\Event\PremiumConfirmed;
use App\Event\PremiumDowngraded;
use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\RecordedDomainEvents;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * Premium reconciliation at competition start
 * ({@see \App\Command\ReconcilePremiumCompetitions\ReconcilePremiumCompetitionsHandler}).
 * See .docs/DOMAIN.md §Monetization.
 */
final class ReconcilePremiumCompetitionsHandlerTest extends IntegrationTestCase
{
    private const string PREMIUM_ID = AppFixtures::PREMIUM_COMPETITION_ID;

    private function reconcile(): void
    {
        $this->commandBus()->dispatch(new ReconcilePremiumCompetitionsCommand());
    }

    /** Make VERIFIED_USER join the premium competition while the owner has no credits ⇒ Uncovered. */
    private function joinUncovered(): void
    {
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            token: AppFixtures::PREMIUM_COMPETITION_LINK_TOKEN,
        ));
    }

    private function grantAdmin(int $amount): void
    {
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            amount: $amount,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    private function enablePremiumPublic(): void
    {
        $this->commandBus()->dispatch(new EnablePremiumCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));
    }

    private function joinPublic(string $userId): void
    {
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString($userId),
            token: AppFixtures::PUBLIC_COMPETITION_LINK_TOKEN,
        ));
    }

    /**
     * @return list<CreditTransaction>
     */
    private function publicRefunds(): array
    {
        /** @var list<CreditTransaction> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.type = :type')
            ->andWhere('t.competition = :competition')
            ->setParameter('type', CreditTransactionType::PremiumRefund)
            ->setParameter('competition', Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID))
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function publicCharge(string $userId): CompetitionPremiumCharge
    {
        $charge = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->where('c.competition = :competition')
            ->andWhere('c.member = :member')
            ->setParameter('competition', Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID))
            ->setParameter('member', Uuid::fromString($userId))
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(CompetitionPremiumCharge::class, $charge);

        return $charge;
    }

    private function competition(string $id): Competition
    {
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString($id));
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function ownerBalance(): int
    {
        $wallet = $this->entityManager()->createQueryBuilder()
            ->select('w')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :user')
            ->setParameter('user', Uuid::fromString(AppFixtures::ADMIN_ID))
            ->getQuery()
            ->getOneOrNullResult();

        return $wallet instanceof CreditWallet ? $wallet->balance : 0;
    }

    private function recorded(): RecordedDomainEvents
    {
        return $this->recordedDomainEvents();
    }

    private function mockClock(): MockClock
    {
        // $this->clock() returns ClockInterface (from the phpstan-excluded base),
        // which narrows cleanly to the test-env MockClock.
        $clock = $this->clock();
        self::assertInstanceOf(MockClock::class, $clock);

        return $clock;
    }

    public function testAllChargedConfirmsAndStaysPremium(): void
    {
        // PREMIUM_COMPETITION: single Charged row, start moment (MATCH_FINISHED
        // kickoff 2025-06-10) already passed vs the fixed clock.
        $this->reconcile();

        $competition = $this->competition(self::PREMIUM_ID);
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertNotNull($competition->premiumReconciledAt);

        self::assertCount(1, $this->recorded()->ofType(PremiumConfirmed::class));

        // Idempotent — a second sweep neither re-confirms nor re-fires.
        $this->recorded()->reset();
        $this->reconcile();

        self::assertCount(0, $this->recorded()->ofType(PremiumConfirmed::class));
        self::assertSame(CompetitionMonetization::Premium, $this->competition(self::PREMIUM_ID)->monetization);
    }

    public function testAnyUncoveredRefundsAllChargedAndDowngradesWithRealLedgerSymmetry(): void
    {
        // FAITHFUL money path — a CLEAN premium competition built via real commands
        // (PUBLIC_COMPETITION, owner ADMIN, owner-only). Grant exactly ONE member's
        // worth, enable premium while empty, then two members JOIN so the join hook
        // does REAL spends: the first is covered (Charged, owner 10→0), the second
        // is not (Uncovered). Refund symmetry is thus asserted against genuine
        // ledger movement, not a fixture Charged row with no backing debit.
        $this->grantAdmin(10);
        $this->enablePremiumPublic();
        $this->joinPublic(AppFixtures::VERIFIED_USER_ID);
        $this->joinPublic(AppFixtures::SECOND_VERIFIED_USER_ID);

        $this->entityManager()->clear();
        self::assertSame(0, $this->ownerBalance());

        $this->recorded()->reset();
        $this->reconcile();

        // Any uncovered ⇒ every Charged row refunded to the manager, downgraded.
        $competition = $this->competition(AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
        self::assertNotNull($competition->premiumReconciledAt);
        self::assertCount(1, $this->recorded()->ofType(PremiumDowngraded::class));

        // Symmetry: owner spent 10 (one real join charge) and is made whole back to
        // the granted 10 — a phantom refund of unspent money would overshoot.
        self::assertSame(10, $this->ownerBalance());
        self::assertCount(1, $this->publicRefunds());
        self::assertSame(10, $this->publicRefunds()[0]->amount);

        // The covered member's charge is Refunded; the uncovered one stays Uncovered.
        self::assertSame(PremiumChargeStatus::Refunded, $this->publicCharge(AppFixtures::VERIFIED_USER_ID)->status);
        self::assertSame(PremiumChargeStatus::Uncovered, $this->publicCharge(AppFixtures::SECOND_VERIFIED_USER_ID)->status);

        // Idempotent re-run: already downgraded ⇒ no second refund, no second event.
        $this->recorded()->reset();
        $this->reconcile();

        self::assertCount(0, $this->recorded()->ofType(PremiumDowngraded::class));
        self::assertCount(1, $this->publicRefunds());
        self::assertSame(10, $this->ownerBalance());
    }

    public function testLateUncoveredJoinAfterReconcileDoesNotReDowngrade(): void
    {
        // Reconcile first (all charged) ⇒ confirmed, stays premium.
        $this->reconcile();
        $reconciledAt = $this->competition(self::PREMIUM_ID)->premiumReconciledAt;
        self::assertNotNull($reconciledAt);

        // A late joiner the owner cannot cover ⇒ Uncovered charge, but the
        // competition already started (is reconciled) so it must NOT re-downgrade.
        $this->recorded()->reset();
        $this->joinUncovered();

        $competition = $this->competition(self::PREMIUM_ID);
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertEquals($reconciledAt, $competition->premiumReconciledAt);

        $lateCharge = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->where('c.competition = :competition')
            ->andWhere('c.member = :member')
            ->setParameter('competition', Uuid::fromString(self::PREMIUM_ID))
            ->setParameter('member', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(CompetitionPremiumCharge::class, $lateCharge);
        self::assertSame(PremiumChargeStatus::Uncovered, $lateCharge->status);

        // A subsequent sweep still must not touch the already-reconciled competition.
        $this->recorded()->reset();
        $this->reconcile();

        self::assertCount(0, $this->recorded()->ofType(PremiumDowngraded::class));
        self::assertSame(CompetitionMonetization::Premium, $this->competition(self::PREMIUM_ID)->monetization);
    }

    public function testNotYetStartedCompetitionIsLeftForLaterTick(): void
    {
        // Turn VERIFIED_COMPETITION premium — its start moment (first kickoff
        // 2025-06-20) is still in the future vs the fixed clock.
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            amount: 50,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
        $this->commandBus()->dispatch(new EnablePremiumCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
        ));

        // Not started yet ⇒ the sweep leaves it un-reconciled.
        $this->reconcile();
        self::assertNull($this->competition(AppFixtures::VERIFIED_COMPETITION_ID)->premiumReconciledAt);
        self::assertSame(CompetitionMonetization::Premium, $this->competition(AppFixtures::VERIFIED_COMPETITION_ID)->monetization);

        // Advance past the first kickoff — the next sweep reconciles it (all charged).
        $this->mockClock()->modify('+10 days');
        $this->reconcile();

        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertNotNull($competition->premiumReconciledAt);
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
    }
}
