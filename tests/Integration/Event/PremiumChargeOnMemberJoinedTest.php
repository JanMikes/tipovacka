<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\CreditTransaction;
use App\Entity\CreditWallet;
use App\Enum\CreditTransactionType;
use App\Enum\PremiumChargeStatus;
use App\Event\PremiumBalanceLow;
use App\Event\PremiumChargeUncovered;
use App\Repository\MembershipRepository;
use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\RecordedDomainEvents;
use Symfony\Component\Uid\Uuid;

/**
 * Premium charge-at-join ({@see \App\Event\ChargePremiumOnMemberJoinedHandler}).
 * The manager (PREMIUM_COMPETITION owner = ADMIN) is charged when a non-owner
 * joins; an insufficient wallet records an Uncovered charge without rolling the
 * join back. See .docs/DOMAIN.md §Monetization.
 */
final class PremiumChargeOnMemberJoinedTest extends IntegrationTestCase
{
    private const string OWNER_ID = AppFixtures::ADMIN_ID;
    private const string JOINER_ID = AppFixtures::VERIFIED_USER_ID;
    private const string COMPETITION_ID = AppFixtures::PREMIUM_COMPETITION_ID;

    private function grantOwner(int $amount): void
    {
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(self::OWNER_ID),
            amount: $amount,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    private function join(): void
    {
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(self::JOINER_ID),
            token: AppFixtures::PREMIUM_COMPETITION_LINK_TOKEN,
        ));
    }

    private function charge(string $memberId): ?CompetitionPremiumCharge
    {
        return $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->where('c.competition = :competition')
            ->andWhere('c.member = :member')
            ->setParameter('competition', Uuid::fromString(self::COMPETITION_ID))
            ->setParameter('member', Uuid::fromString($memberId))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<CreditTransaction>
     */
    private function premiumChargeLedger(string $memberId): array
    {
        /** @var list<CreditTransaction> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.type = :type')
            ->andWhere('t.relatedUser = :member')
            ->setParameter('type', CreditTransactionType::PremiumCharge)
            ->setParameter('member', Uuid::fromString($memberId))
            ->getQuery()
            ->getResult();

        return $rows;
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

    private function recorded(): RecordedDomainEvents
    {
        return $this->recordedDomainEvents();
    }

    private function membershipRepository(): MembershipRepository
    {
        /* @var MembershipRepository */
        return self::getContainer()->get(MembershipRepository::class);
    }

    public function testJoinChargesOwnerAndRecordsChargedRow(): void
    {
        $this->grantOwner(100);

        $this->join();

        $this->entityManager()->clear();

        $charge = $this->charge(self::JOINER_ID);
        self::assertInstanceOf(CompetitionPremiumCharge::class, $charge);
        self::assertSame(PremiumChargeStatus::Charged, $charge->status);
        self::assertSame(10, $charge->amount);
        self::assertNotNull($charge->chargedAt);

        // Exactly one typed ledger row, carrying competition + member references.
        $ledger = $this->premiumChargeLedger(self::JOINER_ID);
        self::assertCount(1, $ledger);
        self::assertSame(-10, $ledger[0]->amount);
        self::assertSame(CreditTransactionType::PremiumCharge, $ledger[0]->type);
        self::assertSame(self::COMPETITION_ID, $ledger[0]->competition?->id->toRfc4122());
        self::assertSame(self::JOINER_ID, $ledger[0]->relatedUser?->id->toRfc4122());

        // Owner (the payer) was debited; the joiner is a member.
        self::assertSame(90, $this->ownerBalance());
        self::assertTrue($this->membershipRepository()->hasActiveMembership(
            Uuid::fromString(self::JOINER_ID),
            Uuid::fromString(self::COMPETITION_ID),
        ));

        // Healthy balance ⇒ no uncovered, no low-balance signal.
        self::assertCount(0, $this->recorded()->ofType(PremiumChargeUncovered::class));
        self::assertCount(0, $this->recorded()->ofType(PremiumBalanceLow::class));
    }

    public function testInsufficientBalanceRecordsUncoveredButMemberStaysJoined(): void
    {
        // Owner has no credits (no wallet seeded) — the charge cannot be covered.
        $this->join();

        $this->entityManager()->clear();

        $charge = $this->charge(self::JOINER_ID);
        self::assertInstanceOf(CompetitionPremiumCharge::class, $charge);
        self::assertSame(PremiumChargeStatus::Uncovered, $charge->status);
        self::assertNull($charge->chargedAt);

        // No premium-charge ledger row was written for the uncovered member.
        self::assertCount(0, $this->premiumChargeLedger(self::JOINER_ID));

        // The join was NOT rolled back — the member is in the competition.
        self::assertTrue($this->membershipRepository()->hasActiveMembership(
            Uuid::fromString(self::JOINER_ID),
            Uuid::fromString(self::COMPETITION_ID),
        ));

        $uncovered = $this->recorded()->ofType(PremiumChargeUncovered::class);
        self::assertCount(1, $uncovered);
        self::assertSame(self::COMPETITION_ID, $uncovered[0]->competitionId->toRfc4122());
        self::assertSame(self::OWNER_ID, $uncovered[0]->ownerId->toRfc4122());
        self::assertSame(self::JOINER_ID, $uncovered[0]->memberId->toRfc4122());
        self::assertSame(10, $uncovered[0]->amount);
    }

    public function testChargeBelowThresholdEmitsBalanceLow(): void
    {
        // 55 credits: covers the 10 charge but drops the balance to 45 (< 50).
        $this->grantOwner(55);

        $this->join();

        $this->entityManager()->clear();

        $charge = $this->charge(self::JOINER_ID);
        self::assertInstanceOf(CompetitionPremiumCharge::class, $charge);
        self::assertSame(PremiumChargeStatus::Charged, $charge->status);
        self::assertSame(45, $this->ownerBalance());

        $low = $this->recorded()->ofType(PremiumBalanceLow::class);
        self::assertCount(1, $low);
        self::assertSame(self::COMPETITION_ID, $low[0]->competitionId->toRfc4122());
        self::assertSame(self::OWNER_ID, $low[0]->ownerId->toRfc4122());
        self::assertSame(45, $low[0]->balance);
    }

    public function testHealthyBalanceDoesNotEmitBalanceLow(): void
    {
        $this->grantOwner(100);

        $this->join();

        self::assertCount(0, $this->recorded()->ofType(PremiumBalanceLow::class));
    }
}
