<?php

declare(strict_types=1);

namespace App\Query\ListCreditTransactions;

use App\Enum\CreditPurchaseStatus;
use App\Enum\CreditTransactionType;
use Symfony\Component\Uid\Uuid;

final readonly class CreditTransactionItem
{
    public function __construct(
        public Uuid $id,
        public int $amount,
        public int $balanceAfter,
        public CreditTransactionType $type,
        public ?string $note,
        public ?string $performedByName,
        public ?CreditPurchaseStatus $purchaseStatus,
        public ?string $invoiceUrl,
        public ?string $invoicePdfUrl,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
