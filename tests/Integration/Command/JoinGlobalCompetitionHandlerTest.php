<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\JoinGlobalCompetition\JoinGlobalCompetitionCommand;
use App\Command\LeaveCompetition\LeaveCompetitionCommand;
use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CreditTransaction;
use App\Entity\Membership;
use App\Enum\CreditTransactionType;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Exception\CompetitionIsNotGlobal;
use App\Exception\InsufficientCredits;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class JoinGlobalCompetitionHandlerTest extends IntegrationTestCase
{
    private const string GLOBAL_ID = AppFixtures::GLOBAL_COMPETITION_ID;
    private const string FREE_GLOBAL_ID = AppFixtures::FREE_GLOBAL_COMPETITION_ID;
    private const string PAYER_ID = AppFixtures::VERIFIED_USER_ID;
    private const string BROKE_ID = AppFixtures::SECOND_VERIFIED_USER_ID;

    public function testPaidJoinChargesEntryFeeAndCreatesMembership(): void
    {
        $this->grantCredits(self::PAYER_ID, 100);

        $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
            userId: Uuid::fromString(self::PAYER_ID),
            competitionId: Uuid::fromString(self::GLOBAL_ID),
        ));

        self::assertTrue($this->isMember(self::PAYER_ID, self::GLOBAL_ID));
        self::assertSame(50, $this->walletBalance(self::PAYER_ID));

        $entryFee = $this->entryFeeTransaction(self::PAYER_ID);
        self::assertInstanceOf(CreditTransaction::class, $entryFee);
        self::assertSame(-50, $entryFee->amount);
        self::assertSame(50, $entryFee->balanceAfter);
        self::assertSame(self::GLOBAL_ID, $entryFee->competition?->id->toRfc4122());
    }

    public function testFreeJoinCreatesNoLedgerEntry(): void
    {
        $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
            userId: Uuid::fromString(self::BROKE_ID),
            competitionId: Uuid::fromString(self::FREE_GLOBAL_ID),
        ));

        self::assertTrue($this->isMember(self::BROKE_ID, self::FREE_GLOBAL_ID));
        self::assertNull($this->entryFeeTransaction(self::BROKE_ID));
    }

    public function testInsufficientCreditsLeavesNoMembershipAndNoLedger(): void
    {
        try {
            $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
                userId: Uuid::fromString(self::BROKE_ID),
                competitionId: Uuid::fromString(self::GLOBAL_ID),
            ));
            self::fail('Expected InsufficientCredits.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InsufficientCredits::class, $this->firstWrappedException($e));
        }

        $this->entityManager()->clear();
        self::assertFalse($this->isMember(self::BROKE_ID, self::GLOBAL_ID));
        self::assertNull($this->entryFeeTransaction(self::BROKE_ID));
    }

    public function testDoubleJoinIsBlocked(): void
    {
        $this->grantCredits(self::PAYER_ID, 200);

        $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
            userId: Uuid::fromString(self::PAYER_ID),
            competitionId: Uuid::fromString(self::GLOBAL_ID),
        ));

        try {
            $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
                userId: Uuid::fromString(self::PAYER_ID),
                competitionId: Uuid::fromString(self::GLOBAL_ID),
            ));
            self::fail('Expected AlreadyMember.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(AlreadyMember::class, $this->firstWrappedException($e));
        }

        // Only the first join was charged.
        self::assertSame(150, $this->walletBalance(self::PAYER_ID));
    }

    public function testRejoinAfterLeaveChargesAgain(): void
    {
        $this->grantCredits(self::PAYER_ID, 100);

        $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
            userId: Uuid::fromString(self::PAYER_ID),
            competitionId: Uuid::fromString(self::GLOBAL_ID),
        ));
        $this->commandBus()->dispatch(new LeaveCompetitionCommand(
            userId: Uuid::fromString(self::PAYER_ID),
            competitionId: Uuid::fromString(self::GLOBAL_ID),
        ));
        $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
            userId: Uuid::fromString(self::PAYER_ID),
            competitionId: Uuid::fromString(self::GLOBAL_ID),
        ));

        self::assertTrue($this->isMember(self::PAYER_ID, self::GLOBAL_ID));
        self::assertSame(0, $this->walletBalance(self::PAYER_ID));
        self::assertSame(2, $this->entryFeeTransactionCount(self::PAYER_ID));
    }

    public function testFinishedSourceBlocksJoin(): void
    {
        $this->grantCredits(self::PAYER_ID, 100);
        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
        ));

        try {
            $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
                userId: Uuid::fromString(self::PAYER_ID),
                competitionId: Uuid::fromString(self::GLOBAL_ID),
            ));
            self::fail('Expected CannotJoinFinishedMatchSource.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CannotJoinFinishedMatchSource::class, $this->firstWrappedException($e));
        }

        $this->entityManager()->clear();
        self::assertFalse($this->isMember(self::PAYER_ID, self::GLOBAL_ID));
    }

    public function testNonGlobalCompetitionIsRejected(): void
    {
        try {
            $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
                userId: Uuid::fromString(self::PAYER_ID),
                competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            ));
            self::fail('Expected CompetitionIsNotGlobal.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionIsNotGlobal::class, $this->firstWrappedException($e));
        }
    }

    private function grantCredits(string $userId, int $amount): void
    {
        $this->commandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString($userId),
            amount: $amount,
            note: 'Testovací kredity',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    private function isMember(string $userId, string $competitionId): bool
    {
        $this->entityManager()->clear();

        return null !== $this->entityManager()->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.competition = :competitionId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function walletBalance(string $userId): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('w.balance')
            ->from(\App\Entity\CreditWallet::class, 'w')
            ->where('w.user = :userId')
            ->setParameter('userId', Uuid::fromString($userId))
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function entryFeeTransaction(string $userId): ?CreditTransaction
    {
        /** @var CreditTransaction|null $transaction */
        $transaction = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->join('t.wallet', 'w')
            ->where('w.user = :userId')
            ->andWhere('t.type = :type')
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('type', CreditTransactionType::EntryFee)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $transaction;
    }

    private function entryFeeTransactionCount(string $userId): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(CreditTransaction::class, 't')
            ->join('t.wallet', 'w')
            ->where('w.user = :userId')
            ->andWhere('t.type = :type')
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('type', CreditTransactionType::EntryFee)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
