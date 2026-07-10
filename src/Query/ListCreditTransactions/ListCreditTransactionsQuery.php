<?php

declare(strict_types=1);

namespace App\Query\ListCreditTransactions;

use App\Entity\CreditTransaction;
use App\Repository\CreditTransactionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListCreditTransactionsQuery
{
    public function __construct(
        private CreditTransactionRepository $transactionRepository,
    ) {
    }

    /**
     * @return list<CreditTransactionItem>
     */
    public function __invoke(ListCreditTransactions $query): array
    {
        $transactions = $this->transactionRepository->findLatestForUser($query->userId, $query->limit);

        return array_values(array_map(
            static fn (CreditTransaction $t): CreditTransactionItem => new CreditTransactionItem(
                id: $t->id,
                amount: $t->amount,
                balanceAfter: $t->balanceAfter,
                type: $t->type,
                note: $t->note,
                performedByName: $t->performedBy?->displayName,
                purchaseStatus: $t->purchase?->status,
                invoiceUrl: $t->purchase?->stripeInvoiceUrl,
                invoicePdfUrl: $t->purchase?->stripeInvoicePdfUrl,
                createdAt: $t->createdAt,
            ),
            $transactions,
        ));
    }
}
