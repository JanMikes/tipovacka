<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\ReconcilePremiumCompetitions\ReconcilePremiumCompetitionsCommand;
use App\Command\SponsorCompetitionPremium\SponsorCompetitionPremiumCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\CreditWallet;
use App\Enum\CompetitionMonetization;
use App\Exception\PremiumSponsorshipRequiresAdmin;
use App\Tests\Support\IntegrationTestCase;
use Doctrine\ORM\Query;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * „Prémium na nás" — an admin grants a partička premium at OUR expense.
 *
 * A global competition could always run premium without any user paying,
 * because an admin owns it. This gives a private group the same, and the whole
 * of what it means is: no charge row is ever created, so the organizer's wallet
 * is irrelevant and the reconciliation at first kickoff finds nothing uncovered.
 *
 * PREMIUM_COMPETITION is the subject: already premium, owner ADMIN has no
 * seeded wallet (so: no credits), SECOND_VERIFIED_USER is a member with one
 * already-Charged row, and VERIFIED_USER is free to join. Its earliest kickoff
 * is in the past against the frozen clock, so the reconcile sweep treats it as
 * started — which is what makes the downgrade tests below real.
 */
final class SponsorCompetitionPremiumHandlerTest extends IntegrationTestCase
{
    public function testSponsoringTurnsOnEveryPremiumFeature(): void
    {
        $this->sponsor();

        $competition = $this->competition();
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertTrue($competition->isPremiumSponsored);

        // A gift of premium with all the features off would be a gift of nothing.
        self::assertTrue($competition->premiumShowDistribution);
        self::assertTrue($competition->premiumShowOthersTips);
        self::assertTrue($competition->premiumAllowTipChanges);
    }

    /**
     * The point of the whole feature: an organizer with an empty wallet, a
     * member joining, and nobody charged a thing.
     */
    public function testAMemberJoiningASponsoredCompetitionIsChargedToNobody(): void
    {
        $this->sponsor();

        $this->join();

        self::assertNull($this->chargeFor(AppFixtures::VERIFIED_USER_ID), 'a sponsored soutěž creates no charge row at all');
    }

    /**
     * The same join WITHOUT the gift, for contrast: the owner cannot cover it,
     * so the row lands Uncovered.
     */
    public function testWithoutSponsorshipTheSameJoinCannotBeCovered(): void
    {
        $this->join();

        $charge = $this->chargeFor(AppFixtures::VERIFIED_USER_ID);
        self::assertNotNull($charge);
        self::assertTrue($charge->isUncovered);
    }

    /**
     * …and what that costs at kickoff. ONE uncovered row downgrades the whole
     * competition the moment it starts — exactly when the group begins playing.
     */
    public function testAnUncoveredJoinDowngradesAnUnsponsoredCompetitionAtStart(): void
    {
        $this->join();

        $this->reconcile();

        self::assertSame(CompetitionMonetization::Boosts, $this->competition()->monetization);
    }

    /** Sponsored, the identical sequence keeps its premium. */
    public function testReconciliationKeepsASponsoredCompetitionPremium(): void
    {
        $this->sponsor();
        $this->join();

        $this->reconcile();

        $competition = $this->competition();
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertNotNull($competition->premiumReconciledAt, 'it was reconciled, not merely skipped');
    }

    /**
     * A group may already carry uncovered rows from before the gift. Left alone
     * they would downgrade it at kickoff anyway, so sponsoring settles them. No
     * credits move: an uncovered charge was never debited in the first place.
     */
    public function testSponsoringForgivesChargesThatCouldNeverBeCovered(): void
    {
        $this->join();
        $charge = $this->chargeFor(AppFixtures::VERIFIED_USER_ID);
        self::assertNotNull($charge);
        self::assertTrue($charge->isUncovered);

        $this->sponsor();

        $settled = $this->chargeFor(AppFixtures::VERIFIED_USER_ID);
        self::assertNotNull($settled);
        self::assertFalse($settled->isUncovered);
        self::assertSame(0, $this->walletBalance(AppFixtures::ADMIN_ID), 'forgiving an uncovered row is not a refund');
    }

    /**
     * Money that really did move is left alone: the member SECOND_VERIFIED_USER
     * paid for is still marked Charged. Handing that back is a refund decision
     * of its own, not a side effect of a gift.
     */
    public function testAlreadyPaidChargesAreNotTouched(): void
    {
        $this->sponsor();

        $charge = $this->chargeFor(AppFixtures::SECOND_VERIFIED_USER_ID);
        self::assertNotNull($charge);
        self::assertTrue($charge->isCharged);
    }

    /** Withdrawing keeps what the group has and bills the NEXT joiner normally. */
    public function testWithdrawingSponsorshipKeepsPremiumAndResumesBilling(): void
    {
        $this->sponsor();
        $this->sponsor(sponsored: false);

        $competition = $this->competition();
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertFalse($competition->isPremiumSponsored);

        $this->join();
        self::assertNotNull($this->chargeFor(AppFixtures::VERIFIED_USER_ID));
    }

    public function testOnlyAnAdminMayGiveOurMoneyAway(): void
    {
        $this->expectException(PremiumSponsorshipRequiresAdmin::class);

        try {
            $this->sponsor(grantedBy: AppFixtures::VERIFIED_USER_ID);
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }
    }

    private function sponsor(bool $sponsored = true, string $grantedBy = AppFixtures::ADMIN_ID): void
    {
        $this->commandBus()->dispatch(new SponsorCompetitionPremiumCommand(
            competitionId: Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
            grantedById: Uuid::fromString($grantedBy),
            sponsored: $sponsored,
        ));
        $this->entityManager()->clear();
    }

    private function join(): void
    {
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            token: AppFixtures::PREMIUM_COMPETITION_LINK_TOKEN,
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));
        $this->entityManager()->clear();
    }

    private function reconcile(): void
    {
        $this->commandBus()->dispatch(new ReconcilePremiumCompetitionsCommand());
        $this->entityManager()->clear();
    }

    private function competition(): Competition
    {
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function chargeFor(string $memberId): ?CompetitionPremiumCharge
    {
        /** @var ?CompetitionPremiumCharge $charge */
        $charge = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->where('c.competition = :competition')
            ->andWhere('c.member = :member')
            ->setParameter('competition', Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID))
            ->setParameter('member', Uuid::fromString($memberId))
            ->getQuery()
            ->getOneOrNullResult();

        return $charge;
    }

    private function walletBalance(string $userId): int
    {
        /** @var ?int $balance */
        $balance = $this->entityManager()->createQueryBuilder()
            ->select('w.balance')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :user')
            ->setParameter('user', Uuid::fromString($userId))
            ->getQuery()
            ->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR);

        return $balance ?? 0;
    }
}
