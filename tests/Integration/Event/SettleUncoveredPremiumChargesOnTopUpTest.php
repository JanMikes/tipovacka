<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\FulfillCreditPurchase\FulfillCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\SettleUncoveredPremiumCharges\SettleUncoveredPremiumChargesCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\CreditTransaction;
use App\Entity\CreditWallet;
use App\Enum\CreditTransactionType;
use App\Enum\PremiumChargeStatus;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

/**
 * Settle-on-top-up: when a premium manager tops up, their Uncovered premium
 * charges are retried ({@see \App\Event\SettleUncoveredPremiumChargesOnTopUpHandler}
 * → {@see \App\Command\SettleUncoveredPremiumCharges\SettleUncoveredPremiumChargesHandler},
 * which is async-routed). See .docs/DOMAIN.md §Monetization.
 */
final class SettleUncoveredPremiumChargesOnTopUpTest extends IntegrationTestCase
{
    private function async(): InMemoryTransport
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound

        return $transport;
    }

    /** Drains + locally handles every enqueued settle command (as the worker would). */
    private function processEnqueuedSettles(): int
    {
        $count = 0;

        foreach ($this->async()->getSent() as $envelope) {
            $message = $envelope->getMessage();

            if ($message instanceof SettleUncoveredPremiumChargesCommand) {
                ++$count;
                $this->commandBus()->dispatch(new Envelope($message, [new ReceivedStamp('async')]));
            }
        }

        return $count;
    }

    public function testTopUpSettlesUncoveredCharge(): void
    {
        $this->async()->reset();

        // 1) VERIFIED_USER joins the premium competition while owner ADMIN has no
        //    credits ⇒ an Uncovered charge is recorded.
        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            token: AppFixtures::PREMIUM_COMPETITION_LINK_TOKEN,
        ));

        // 2) ADMIN buys credits — CreditsPurchased fires the on-top-up settle.
        $envelope = $this->commandBus()->dispatch(new InitiateCreditPurchaseCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            credits: 100,
            successUrl: 'https://wtips.test/navrat?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: 'https://wtips.test/navrat?cancelled=1',
        ));
        $checkout = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(InitiatedCreditCheckout::class, $checkout);

        $this->paymentGateway()->primePaidSession($checkout->purchase->stripeCheckoutSessionId, amountTotal: 10000);
        $this->commandBus()->dispatch(new FulfillCreditPurchaseCommand($checkout->purchase->stripeCheckoutSessionId));

        // 3) The settle command was enqueued async — drain + handle it.
        self::assertGreaterThanOrEqual(1, $this->processEnqueuedSettles());

        $em = $this->entityManager();
        $em->clear();

        // The previously Uncovered charge is now Charged.
        $charge = $em->createQueryBuilder()
            ->select('c')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->where('c.competition = :competition')
            ->andWhere('c.member = :member')
            ->setParameter('competition', Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID))
            ->setParameter('member', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(CompetitionPremiumCharge::class, $charge);
        self::assertSame(PremiumChargeStatus::Charged, $charge->status);

        // Owner debited 10 from the 100 top-up.
        $wallet = $em->createQueryBuilder()
            ->select('w')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :user')
            ->setParameter('user', Uuid::fromString(AppFixtures::ADMIN_ID))
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(CreditWallet::class, $wallet);
        self::assertSame(90, $wallet->balance);

        // Exactly one premium-charge ledger row for the settled member, with refs.
        /** @var list<CreditTransaction> $ledger */
        $ledger = $em->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->where('t.type = :type')
            ->andWhere('t.relatedUser = :member')
            ->setParameter('type', CreditTransactionType::PremiumCharge)
            ->setParameter('member', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->getQuery()
            ->getResult();
        self::assertCount(1, $ledger);
        self::assertSame(-10, $ledger[0]->amount);
        self::assertSame(AppFixtures::PREMIUM_COMPETITION_ID, $ledger[0]->competition?->id->toRfc4122());
    }
}
