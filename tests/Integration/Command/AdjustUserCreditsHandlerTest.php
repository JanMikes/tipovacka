<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CreditTransaction;
use App\Enum\CreditTransactionType;
use App\Exception\InsufficientCredits;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class AdjustUserCreditsHandlerTest extends IntegrationTestCase
{
    private function adjust(int $amount, string $note = 'Test poznámka'): void
    {
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            amount: $amount,
            note: $note,
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    public function testGrantCreatesWalletAndAuditedTransaction(): void
    {
        $this->adjust(500, 'Kompenzace za výpadek');

        $em = $this->entityManager();
        $em->clear();

        /** @var CreditTransaction|null $transaction */
        $transaction = $em->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(CreditTransaction::class, $transaction);
        self::assertSame(500, $transaction->amount);
        self::assertSame(500, $transaction->balanceAfter);
        self::assertSame(CreditTransactionType::AdminAdjustment, $transaction->type);
        self::assertSame('Kompenzace za výpadek', $transaction->note);
        self::assertSame(AppFixtures::ADMIN_ID, $transaction->performedBy?->id->toRfc4122());
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $transaction->wallet->user->id->toRfc4122());
        self::assertSame(500, $transaction->wallet->balance);
    }

    public function testNegativeAdjustmentWithinBalance(): void
    {
        $this->adjust(500);
        $this->adjust(-200, 'Korekce');

        $em = $this->entityManager();
        $em->clear();

        $balances = $em->createQueryBuilder()
            ->select('t.balanceAfter')
            ->from(CreditTransaction::class, 't')
            ->orderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        self::assertSame([500, 300], $balances);
    }

    public function testAdjustmentBelowZeroIsRejected(): void
    {
        $this->adjust(100);

        try {
            $this->adjust(-101);
            self::fail('Expected InsufficientCredits.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InsufficientCredits::class, $this->firstWrappedException($e));
        }
    }
}
