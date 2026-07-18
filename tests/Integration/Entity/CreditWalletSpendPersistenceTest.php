<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CreditTransaction;
use App\Entity\User;
use App\Enum\CreditTransactionType;
use App\Repository\CreditTransactionRepository;
use App\Repository\CreditWalletRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CreditWalletSpendPersistenceTest extends IntegrationTestCase
{
    public function testSpendTransactionPersistsAllColumns(): void
    {
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            amount: 500,
            note: 'Init',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $em = $this->entityManager();

        /** @var CreditWalletRepository $walletRepository */
        $walletRepository = self::getContainer()->get(CreditWalletRepository::class);
        $wallet = $walletRepository->findByUserId(Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($wallet);

        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        $relatedUser = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertInstanceOf(User::class, $relatedUser);

        $transactionId = Uuid::v7();
        $transaction = $wallet->spend(
            transactionId: $transactionId,
            amount: 120,
            type: CreditTransactionType::BoostPurchase,
            now: $this->clock()->now(),
            competition: $competition,
            relatedUser: $relatedUser,
            boostType: 'tip_change',
            note: 'Testovací útrata',
        );

        /** @var CreditTransactionRepository $transactionRepository */
        $transactionRepository = self::getContainer()->get(CreditTransactionRepository::class);
        $transactionRepository->save($transaction);
        $em->flush();
        $em->clear();

        $reloaded = $em->find(CreditTransaction::class, $transactionId);
        self::assertInstanceOf(CreditTransaction::class, $reloaded);
        self::assertSame(-120, $reloaded->amount);
        self::assertSame(380, $reloaded->balanceAfter);
        self::assertSame(CreditTransactionType::BoostPurchase, $reloaded->type);
        self::assertSame('Testovací útrata', $reloaded->note);
        self::assertSame(AppFixtures::VERIFIED_COMPETITION_ID, $reloaded->competition?->id->toRfc4122());
        self::assertSame(AppFixtures::SECOND_VERIFIED_USER_ID, $reloaded->relatedUser?->id->toRfc4122());
        self::assertSame('tip_change', $reloaded->boostType);
        self::assertNull($reloaded->performedBy);
        self::assertNull($reloaded->purchase);
        self::assertSame(380, $reloaded->wallet->balance);
    }
}
