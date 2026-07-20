<?php

declare(strict_types=1);

namespace App\Query\ListAllCreditTransactions;

final readonly class AdminCreditLedgerResult
{
    /**
     * @param list<AdminCreditTransactionItem>         $transactions filtered rows, newest first
     * @param list<AdminCreditLedgerCompetitionOption> $competitions every competition with ledger activity (filter options)
     */
    public function __construct(
        public array $transactions,
        public array $competitions,
    ) {
    }
}
