<?php

declare(strict_types=1);

namespace App\Query\ListAllCreditTransactions;

use App\Entity\CreditTransaction;
use App\Enum\BoostType;
use App\Repository\CreditTransactionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListAllCreditTransactionsQuery
{
    public function __construct(
        private CreditTransactionRepository $transactionRepository,
    ) {
    }

    public function __invoke(ListAllCreditTransactions $query): AdminCreditLedgerResult
    {
        $transactions = array_map(
            static fn (CreditTransaction $t): AdminCreditTransactionItem => new AdminCreditTransactionItem(
                id: $t->id,
                walletOwnerId: $t->wallet->user->id,
                walletOwnerName: $t->wallet->user->displayName,
                amount: $t->amount,
                balanceAfter: $t->balanceAfter,
                type: $t->type,
                note: $t->note,
                performedByName: $t->performedBy?->displayName,
                competitionName: $t->competition?->name,
                boostType: null !== $t->boostType ? BoostType::tryFrom($t->boostType) : null,
                relatedUserName: $t->relatedUser?->displayName,
                createdAt: $t->createdAt,
            ),
            $this->transactionRepository->findLatest($query->type, $query->competitionId, $query->limit),
        );

        $competitions = array_map(
            static fn (array $row): AdminCreditLedgerCompetitionOption => new AdminCreditLedgerCompetitionOption(
                id: $row['id'],
                name: $row['name'],
            ),
            $this->transactionRepository->findReferencedCompetitions(),
        );

        return new AdminCreditLedgerResult(
            transactions: $transactions,
            competitions: $competitions,
        );
    }
}
