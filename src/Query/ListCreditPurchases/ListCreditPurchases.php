<?php

declare(strict_types=1);

namespace App\Query\ListCreditPurchases;

use App\Enum\CreditPurchaseStatus;
use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * All purchases without filters (admin overview), or narrowed to one user
 * and/or status (e.g. the portal's "payment in progress" banner).
 *
 * @implements QueryMessage<list<CreditPurchaseItem>>
 */
final readonly class ListCreditPurchases implements QueryMessage
{
    public function __construct(
        public ?Uuid $userId = null,
        public ?CreditPurchaseStatus $status = null,
        public int $limit = 200,
    ) {
    }
}
