<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\EnablePremium\EnablePremiumCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\BoostPurchase;
use App\Entity\Competition;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\CreditTransaction;
use App\Enum\CompetitionMonetization;
use App\Enum\CreditTransactionType;
use App\Enum\PremiumChargeStatus;
use App\Event\BoostRefunded;
use App\Exception\InsufficientCredits;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * Re-enabling premium ({@see \App\Command\EnablePremium\EnablePremiumHandler}) —
 * atomic all-or-nothing group charge. See .docs/DOMAIN.md §Monetization.
 */
final class EnablePremiumHandlerTest extends IntegrationTestCase
{
    private function grant(string $userId, int $amount): void
    {
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString($userId),
            amount: $amount,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    private function enable(string $competitionId, string $editorId): void
    {
        $this->commandBus()->dispatch(new EnablePremiumCommand(
            editorId: Uuid::fromString($editorId),
            competitionId: Uuid::fromString($competitionId),
        ));
    }

    /**
     * @return list<CompetitionPremiumCharge>
     */
    private function charges(string $competitionId): array
    {
        /** @var list<CompetitionPremiumCharge> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->where('c.competition = :competition')
            ->setParameter('competition', Uuid::fromString($competitionId))
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<CreditTransaction>
     */
    private function premiumChargeLedger(string $competitionId): array
    {
        /** @var list<CreditTransaction> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.type = :type')
            ->andWhere('t.competition = :competition')
            ->setParameter('type', CreditTransactionType::PremiumCharge)
            ->setParameter('competition', Uuid::fromString($competitionId))
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function competition(string $competitionId): Competition
    {
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString($competitionId));
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    public function testEnableWithNoNonOwnerMembersJustFlipsMonetization(): void
    {
        // PUBLIC_COMPETITION: ADMIN is the sole member (owner) ⇒ nothing to charge.
        $this->enable(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::ADMIN_ID);

        $this->entityManager()->clear();

        $competition = $this->competition(AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertNull($competition->premiumReconciledAt);
        self::assertCount(0, $this->charges(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertCount(0, $this->premiumChargeLedger(AppFixtures::PUBLIC_COMPETITION_ID));
    }

    public function testEnableChargesWholeGroupAsOneDebit(): void
    {
        // VERIFIED_COMPETITION already has ANONYMOUS as a non-owner member; add a
        // second so the group charge is 2 × 10 = 20.
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            token: AppFixtures::VERIFIED_COMPETITION_LINK_TOKEN,
        ));
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);

        $this->enable(AppFixtures::VERIFIED_COMPETITION_ID, AppFixtures::VERIFIED_USER_ID);

        $this->entityManager()->clear();

        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertNull($competition->premiumReconciledAt);

        // Two Charged rows (the two non-owner members); the owner is NOT charged.
        $charges = $this->charges(AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertCount(2, $charges);
        foreach ($charges as $charge) {
            self::assertSame(PremiumChargeStatus::Charged, $charge->status);
            self::assertNotSame(AppFixtures::VERIFIED_USER_ID, $charge->member->id->toRfc4122());
        }

        // The whole group is a SINGLE ledger debit of the total, not one per member.
        $ledger = $this->premiumChargeLedger(AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertCount(1, $ledger);
        self::assertSame(-20, $ledger[0]->amount);
        self::assertNull($ledger[0]->relatedUser);
    }

    public function testEnableInsufficientBalanceThrowsNamingTotalAndWritesNothing(): void
    {
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            token: AppFixtures::VERIFIED_COMPETITION_LINK_TOKEN,
        ));
        // Needs 20, has only 15 ⇒ all-or-nothing failure.
        $this->grant(AppFixtures::VERIFIED_USER_ID, 15);

        try {
            $this->enable(AppFixtures::VERIFIED_COMPETITION_ID, AppFixtures::VERIFIED_USER_ID);
            self::fail('Expected InsufficientCredits.');
        } catch (HandlerFailedException $e) {
            $inner = $this->firstWrappedException($e);
            self::assertInstanceOf(InsufficientCredits::class, $inner);
            // The friendly message names the exact total and the current balance.
            self::assertStringContainsString('20', $inner->getMessage());
            self::assertStringContainsString('15', $inner->getMessage());
        }

        $this->entityManager()->clear();

        // Nothing was written — the competition is still un-monetized, no charge rows.
        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertSame(CompetitionMonetization::None, $competition->monetization);
        self::assertCount(0, $this->charges(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertCount(0, $this->premiumChargeLedger(AppFixtures::VERIFIED_COMPETITION_ID));
    }

    public function testEnablingPremiumOnBoostsCompetitionRefundsActiveBoosts(): void
    {
        // BOOSTS_COMPETITION: ADMIN owns it, SECOND_VERIFIED_USER (the sole non-owner
        // member) holds an active OthersTips boost (fixture, pricePaid 20). Enabling
        // premium charges ADMIN for the member and refunds the boost to SECOND.
        $this->grant(AppFixtures::ADMIN_ID, 100);

        $this->enable(AppFixtures::BOOSTS_COMPETITION_ID, AppFixtures::ADMIN_ID);

        $this->entityManager()->clear();

        $competition = $this->competition(AppFixtures::BOOSTS_COMPETITION_ID);
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);

        // The fixture boost is now refunded (no active boost remains).
        /** @var list<BoostPurchase> $active */
        $active = $this->entityManager()->createQueryBuilder()
            ->select('b')
            ->from(BoostPurchase::class, 'b')
            ->where('b.competition = :competition')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('competition', Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID))
            ->getQuery()
            ->getResult();
        self::assertCount(0, $active);

        // A BoostRefund ledger row credited the buyer (VERIFIED_USER) 20 back.
        /** @var list<CreditTransaction> $refunds */
        $refunds = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.type = :type')
            ->andWhere('t.competition = :competition')
            ->setParameter('type', CreditTransactionType::BoostRefund)
            ->setParameter('competition', Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID))
            ->getQuery()
            ->getResult();
        self::assertCount(1, $refunds);
        self::assertSame(20, $refunds[0]->amount);
        self::assertSame('others_tips', $refunds[0]->boostType);
        self::assertSame(AppFixtures::SECOND_VERIFIED_USER_ID, $refunds[0]->wallet->user->id->toRfc4122());

        // A BoostRefunded domain event was dispatched for the buyer.
        $events = $this->recordedDomainEvents()->ofType(BoostRefunded::class);
        self::assertCount(1, $events);
        self::assertSame(AppFixtures::SECOND_VERIFIED_USER_ID, $events[0]->userId->toRfc4122());
        self::assertSame('others_tips', $events[0]->boostType);
        self::assertSame(20, $events[0]->amount);
    }
}
