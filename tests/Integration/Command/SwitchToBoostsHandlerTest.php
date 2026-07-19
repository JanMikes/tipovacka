<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

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
 * Switching a premium competition to boosts
 * ({@see \App\Command\SwitchToBoosts\SwitchToBoostsHandler}) refunds every Charged
 * premium row to the manager. See .docs/DOMAIN.md §Monetization.
 */
final class SwitchToBoostsHandlerTest extends IntegrationTestCase
{
    public function testSwitchRefundsChargedRowsAndFlipsMonetization(): void
    {
        $this->commandBus()->dispatch(new SwitchToBoostsCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        // The fixture's Charged row (member = SECOND_VERIFIED_USER, 10) is refunded.
        $charge = $em->find(CompetitionPremiumCharge::class, Uuid::fromString(AppFixtures::PREMIUM_CHARGE_ID));
        self::assertInstanceOf(CompetitionPremiumCharge::class, $charge);
        self::assertSame(PremiumChargeStatus::Refunded, $charge->status);
        self::assertNotNull($charge->refundedAt);

        // Monetization flipped to boosts; the reconciliation stamp is left alone
        // (a switch is not a reconciliation).
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
        self::assertNull($competition->premiumReconciledAt);

        // Exactly one PremiumRefund ledger row, carrying the references, credited
        // to the manager's wallet.
        /** @var list<CreditTransaction> $refunds */
        $refunds = $em->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.type = :type')
            ->setParameter('type', CreditTransactionType::PremiumRefund)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $refunds);
        self::assertSame(10, $refunds[0]->amount);
        self::assertSame(AppFixtures::PREMIUM_COMPETITION_ID, $refunds[0]->competition?->id->toRfc4122());
        self::assertSame(AppFixtures::SECOND_VERIFIED_USER_ID, $refunds[0]->relatedUser?->id->toRfc4122());

        $wallet = $em->createQueryBuilder()
            ->select('w')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :user')
            ->setParameter('user', Uuid::fromString(AppFixtures::ADMIN_ID))
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(CreditWallet::class, $wallet);
        self::assertSame(10, $wallet->balance);
    }
}
