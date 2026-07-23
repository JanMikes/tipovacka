<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\PurchaseBoost\PurchaseBoostCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\BoostPurchase;
use App\Entity\CreditTransaction;
use App\Enum\BoostType;
use App\Enum\CreditTransactionType;
use App\Exception\BoostNotAvailable;
use App\Exception\InsufficientCredits;
use App\Exception\NotAMember;
use App\Repository\CreditWalletRepository;
use App\Service\Credits\PricingConfig;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * PurchaseBoost — guards + single wallet debit + row. SECOND_VERIFIED_USER is the
 * fixture BOOSTS member (already owns OthersTips). See .docs/DOMAIN.md §Monetization.
 */
final class PurchaseBoostHandlerTest extends IntegrationTestCase
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

    private function joinBoosts(string $userId): void
    {
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString($userId),
            token: AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ));
    }

    private function purchase(string $userId, string $competitionId, BoostType $type): void
    {
        $this->commandBus()->dispatch(new PurchaseBoostCommand(
            userId: Uuid::fromString($userId),
            competitionId: Uuid::fromString($competitionId),
            type: $type,
        ));
    }

    /**
     * @return list<BoostPurchase>
     */
    private function activeBoosts(string $userId, string $competitionId): array
    {
        /** @var list<BoostPurchase> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('b')
            ->from(BoostPurchase::class, 'b')
            ->where('b.user = :user')
            ->andWhere('b.competition = :competition')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('user', Uuid::fromString($userId))
            ->setParameter('competition', Uuid::fromString($competitionId))
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<CreditTransaction>
     */
    private function boostLedger(string $competitionId): array
    {
        /** @var list<CreditTransaction> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.type = :type')
            ->andWhere('t.competition = :competition')
            ->setParameter('type', CreditTransactionType::BoostPurchase)
            ->setParameter('competition', Uuid::fromString($competitionId))
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function balance(string $userId): int
    {
        $wallet = self::getContainer()->get(CreditWalletRepository::class)->findByUserId(Uuid::fromString($userId));

        return $wallet->balance ?? 0;
    }

    public function testHappyPathWritesOneLedgerRowAndOneBoostRow(): void
    {
        // SECOND_VERIFIED_USER is a member (already owns OthersTips); buys the
        // distinct TipChange boost (40).
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        $this->purchase(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::TipChange);

        $this->entityManager()->clear();

        // OthersTips (fixture) + the new TipChange.
        self::assertCount(2, $this->activeBoosts(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID));

        $ledger = $this->boostLedger(AppFixtures::BOOSTS_COMPETITION_ID);
        self::assertCount(1, $ledger);
        self::assertSame(-40, $ledger[0]->amount);
        self::assertSame('tip_change', $ledger[0]->boostType);
        self::assertNotNull($ledger[0]->competition);
        self::assertSame(AppFixtures::BOOSTS_COMPETITION_ID, $ledger[0]->competition->id->toRfc4122());
        self::assertNull($ledger[0]->relatedUser);

        self::assertSame(60, $this->balance(AppFixtures::SECOND_VERIFIED_USER_ID));
    }

    public function testWrongMonetizationIsBlocked(): void
    {
        // PREMIUM_COMPETITION is monetization=premium — no boosts sold.
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        try {
            $this->purchase(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::PREMIUM_COMPETITION_ID, BoostType::TipChange);
            self::fail('Expected BoostNotAvailable.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(BoostNotAvailable::class, $this->firstWrappedException($e));
        }

        $this->entityManager()->clear();
        self::assertCount(0, $this->activeBoosts(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::PREMIUM_COMPETITION_ID));
    }

    public function testNonMemberIsBlocked(): void
    {
        // UNVERIFIED_USER is not a member of BOOSTS_COMPETITION.
        $this->grant(AppFixtures::UNVERIFIED_USER_ID, 100);

        try {
            $this->purchase(AppFixtures::UNVERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::TipChange);
            self::fail('Expected NotAMember.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(NotAMember::class, $this->firstWrappedException($e));
        }

        $this->entityManager()->clear();
        self::assertCount(0, $this->activeBoosts(AppFixtures::UNVERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID));
    }

    public function testDuplicateActiveTypeIsBlocked(): void
    {
        // SECOND_VERIFIED_USER already owns an active OthersTips in fixtures.
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        try {
            $this->purchase(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::OthersTips);
            self::fail('Expected BoostNotAvailable.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(BoostNotAvailable::class, $this->firstWrappedException($e));
        }

        $this->entityManager()->clear();
        // No purchase transaction written.
        self::assertCount(0, $this->boostLedger(AppFixtures::BOOSTS_COMPETITION_ID));
    }

    public function testOthersTipsWhileOwningDistributionChargesFullPriceAndIsAllowed(): void
    {
        // A fresh joiner (VERIFIED_USER) owns neither boost: buys TipDistribution
        // then OthersTips — both allowed, full prices.
        $this->joinBoosts(AppFixtures::VERIFIED_USER_ID);
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);

        $this->purchase(AppFixtures::VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::TipDistribution);
        $this->purchase(AppFixtures::VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::OthersTips);

        $this->entityManager()->clear();

        self::assertCount(2, $this->activeBoosts(AppFixtures::VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID));
        // Full prices: 10 + 20 = 30 spent (no differential discount).
        self::assertSame(70, $this->balance(AppFixtures::VERIFIED_USER_ID));
    }

    public function testTipDistributionWhileOwningOthersTipsIsBlockedBySuperset(): void
    {
        // SECOND_VERIFIED_USER owns OthersTips (superset) ⇒ distribution already covered.
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        try {
            $this->purchase(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::TipDistribution);
            self::fail('Expected BoostNotAvailable (superseded).');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(BoostNotAvailable::class, $this->firstWrappedException($e));
        }

        $this->entityManager()->clear();
        self::assertCount(0, $this->boostLedger(AppFixtures::BOOSTS_COMPETITION_ID));
    }

    public function testManagerBuysVisibilityBoostsLikeAnyOtherPlayer(): void
    {
        // ADMIN owns BOOSTS_COMPETITION but gets no free sight of anyone's tips
        // (2026-07-23) — the organizer plays too, so they pay the same as members.
        $this->grant(AppFixtures::ADMIN_ID, 100);

        $this->purchase(AppFixtures::ADMIN_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::TipDistribution);

        $this->entityManager()->clear();

        self::assertCount(1, $this->activeBoosts(AppFixtures::ADMIN_ID, AppFixtures::BOOSTS_COMPETITION_ID));
        self::assertSame(100 - PricingConfig::BOOST_TIP_DISTRIBUTION, $this->balance(AppFixtures::ADMIN_ID));

        // …and a second attempt at what they now own is still rejected.
        try {
            $this->purchase(AppFixtures::ADMIN_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::TipDistribution);
            self::fail('Expected BoostNotAvailable for an already-owned boost.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(BoostNotAvailable::class, $this->firstWrappedException($e));
        }
    }

    public function testManagerCanStillBuyTipChangeBoost(): void
    {
        // tip_change is NOT auto-granted to managers (subject to the tip freeze), so
        // the owner may still buy it to keep changing their own tips after lock.
        $this->grant(AppFixtures::ADMIN_ID, 100);

        $this->purchase(AppFixtures::ADMIN_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::TipChange);

        $this->entityManager()->clear();

        self::assertCount(1, $this->activeBoosts(AppFixtures::ADMIN_ID, AppFixtures::BOOSTS_COMPETITION_ID));
        self::assertSame(60, $this->balance(AppFixtures::ADMIN_ID));
    }

    public function testInsufficientCreditsWritesNoRow(): void
    {
        // SECOND_VERIFIED_USER has balance 0 — cannot afford TipChange (40).
        try {
            $this->purchase(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID, BoostType::TipChange);
            self::fail('Expected InsufficientCredits.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InsufficientCredits::class, $this->firstWrappedException($e));
        }

        $this->entityManager()->clear();
        // No purchase transaction, and only the fixture OthersTips remains (no TipChange added).
        self::assertCount(0, $this->boostLedger(AppFixtures::BOOSTS_COMPETITION_ID));
        self::assertCount(1, $this->activeBoosts(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::BOOSTS_COMPETITION_ID));
    }
}
