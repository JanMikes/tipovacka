<?php

declare(strict_types=1);

namespace App\Query\ListAllCreditTransactions;

use App\Enum\CreditTransactionType;
use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Admin-wide credit ledger across all wallets, optionally filtered by transaction
 * type and/or competition. Backs the „Kredity → Transakce" admin view.
 *
 * @implements QueryMessage<AdminCreditLedgerResult>
 */
final readonly class ListAllCreditTransactions implements QueryMessage
{
    public function __construct(
        public ?CreditTransactionType $type = null,
        public ?Uuid $competitionId = null,
        public int $limit = 200,
    ) {
    }
}
