<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\EnablePremium\EnablePremiumCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\LeaveCompetition\LeaveCompetitionCommand;
use App\Command\SwitchToBoosts\SwitchToBoostsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\CreditTransaction;
use App\Entity\CreditWallet;
use App\Enum\CompetitionMonetization;
use App\Enum\CreditTransactionType;
use App\Enum\PremiumChargeStatus;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Faithful rejoin money test — a CLEAN premium competition built entirely from
 * real commands (no unbacked fixture charge), so refund symmetry is asserted
 * against genuine ledger movement. PUBLIC_COMPETITION (owner ADMIN, owner is the
 * sole member) is turned premium while empty, then a member JOINS so the join
 * hook does a real spend. See .docs/DOMAIN.md §Monetization
 * (rejoin-does-not-recharge-an-already-paid-slot).
 */
final class PremiumRejoinChargeTest extends IntegrationTestCase
{
    private const string OWNER_ID = AppFixtures::ADMIN_ID;
    private const string MEMBER_ID = AppFixtures::VERIFIED_USER_ID;
    private const string COMPETITION_ID = AppFixtures::PUBLIC_COMPETITION_ID;

    private function grantOwner(int $amount): void
    {
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(self::OWNER_ID),
            amount: $amount,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    private function enablePremium(): void
    {
        $this->commandBus()->dispatch(new EnablePremiumCommand(
            editorId: Uuid::fromString(self::OWNER_ID),
            competitionId: Uuid::fromString(self::COMPETITION_ID),
        ));
    }

    private function join(): void
    {
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(self::MEMBER_ID),
            token: AppFixtures::PUBLIC_COMPETITION_LINK_TOKEN,
        ));
    }

    private function leave(): void
    {
        $this->commandBus()->dispatch(new LeaveCompetitionCommand(
            userId: Uuid::fromString(self::MEMBER_ID),
            competitionId: Uuid::fromString(self::COMPETITION_ID),
        ));
    }

    private function switchToBoosts(): void
    {
        $this->commandBus()->dispatch(new SwitchToBoostsCommand(
            editorId: Uuid::fromString(self::OWNER_ID),
            competitionId: Uuid::fromString(self::COMPETITION_ID),
        ));
    }

    private function ownerBalance(): int
    {
        $wallet = $this->entityManager()->createQueryBuilder()
            ->select('w')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :user')
            ->setParameter('user', Uuid::fromString(self::OWNER_ID))
            ->getQuery()
            ->getOneOrNullResult();

        return $wallet instanceof CreditWallet ? $wallet->balance : 0;
    }

    /**
     * The member's premium-charge ledger DEBITS (real wallet spends carrying the
     * member reference). Exactly one must ever exist for a single paid slot.
     *
     * @return list<CreditTransaction>
     */
    private function memberChargeDebits(): array
    {
        /** @var list<CreditTransaction> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.type = :type')
            ->andWhere('t.relatedUser = :member')
            ->setParameter('type', CreditTransactionType::PremiumCharge)
            ->setParameter('member', Uuid::fromString(self::MEMBER_ID))
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<CreditTransaction>
     */
    private function premiumRefunds(): array
    {
        /** @var list<CreditTransaction> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.type = :type')
            ->andWhere('t.competition = :competition')
            ->setParameter('type', CreditTransactionType::PremiumRefund)
            ->setParameter('competition', Uuid::fromString(self::COMPETITION_ID))
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function charge(): ?CompetitionPremiumCharge
    {
        return $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->where('c.competition = :competition')
            ->andWhere('c.member = :member')
            ->setParameter('competition', Uuid::fromString(self::COMPETITION_ID))
            ->setParameter('member', Uuid::fromString(self::MEMBER_ID))
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function competitionMonetization(): CompetitionMonetization
    {
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(self::COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);

        return $competition->monetization;
    }

    public function testRejoinDoesNotRechargeAnAlreadyPaidSlotAndRefundSymmetryHolds(): void
    {
        // Clean premium competition via real commands: grant 100, enable premium
        // while owner-only (nothing to charge yet — owner stays 100).
        $this->grantOwner(100);
        $this->enablePremium();

        $this->entityManager()->clear();
        self::assertSame(CompetitionMonetization::Premium, $this->competitionMonetization());
        self::assertSame(100, $this->ownerBalance());
        self::assertNull($this->charge());

        // Member joins → the join hook does a REAL spend of 10 (owner 100 → 90).
        $this->join();

        $this->entityManager()->clear();
        $charge = $this->charge();
        self::assertInstanceOf(CompetitionPremiumCharge::class, $charge);
        self::assertSame(PremiumChargeStatus::Charged, $charge->status);
        self::assertCount(1, $this->memberChargeDebits());
        self::assertSame(90, $this->ownerBalance());

        // Leave (never refunds — the charge row stays Charged) then rejoin.
        $this->leave();
        $this->join();

        $this->entityManager()->clear();

        // The paid slot must NOT be re-charged: still exactly ONE premium-charge
        // debit and the owner is still 90 (NOT 80).
        self::assertCount(1, $this->memberChargeDebits());
        self::assertSame(90, $this->ownerBalance());
        $rejoined = $this->charge();
        self::assertInstanceOf(CompetitionPremiumCharge::class, $rejoined);
        self::assertSame(PremiumChargeStatus::Charged, $rejoined->status);

        // Downgrade refunds the single Charged row against the real debit ⇒ the
        // owner is made whole back to 100 (genuine refund symmetry).
        $this->switchToBoosts();

        $this->entityManager()->clear();
        self::assertSame(CompetitionMonetization::Boosts, $this->competitionMonetization());
        self::assertSame(100, $this->ownerBalance());

        $refunds = $this->premiumRefunds();
        self::assertCount(1, $refunds);
        self::assertSame(10, $refunds[0]->amount);
        self::assertSame(self::MEMBER_ID, $refunds[0]->relatedUser?->id->toRfc4122());

        $refundedCharge = $this->charge();
        self::assertInstanceOf(CompetitionPremiumCharge::class, $refundedCharge);
        self::assertSame(PremiumChargeStatus::Refunded, $refundedCharge->status);
    }
}
