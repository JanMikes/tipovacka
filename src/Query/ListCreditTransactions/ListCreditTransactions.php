<?php

declare(strict_types=1);

namespace App\Query\ListCreditTransactions;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<CreditTransactionItem>>
 */
final readonly class ListCreditTransactions implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        public int $limit = 100,
    ) {
    }
}
