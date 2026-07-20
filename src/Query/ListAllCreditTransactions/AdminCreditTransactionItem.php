<?php

declare(strict_types=1);

namespace App\Query\ListAllCreditTransactions;

use App\Enum\BoostType;
use App\Enum\CreditTransactionType;
use Symfony\Component\Uid\Uuid;

/**
 * One ledger row for the admin-wide credit ledger. Unlike the per-user
 * {@see \App\Query\ListCreditTransactions\CreditTransactionItem} this carries the
 * wallet owner so an admin can see whose balance moved.
 */
final readonly class AdminCreditTransactionItem
{
    public function __construct(
        public Uuid $id,
        public Uuid $walletOwnerId,
        public string $walletOwnerName,
        public int $amount,
        public int $balanceAfter,
        public CreditTransactionType $type,
        public ?string $note,
        public ?string $performedByName,
        public ?string $competitionName,
        public ?BoostType $boostType,
        public ?string $relatedUserName,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
