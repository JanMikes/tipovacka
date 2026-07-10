<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CreditTransactionType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Immutable ledger entry — the audit trail of every credit movement.
 * Instances are created exclusively through CreditWallet methods so the
 * recorded balanceAfter always matches the wallet balance.
 */
#[ORM\Entity]
#[ORM\Table(name: 'credit_transactions')]
#[ORM\Index(columns: ['wallet_id', 'created_at'], name: 'IDX_credit_transactions_wallet_created')]
class CreditTransaction
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: CreditWallet::class)]
        #[ORM\JoinColumn(name: 'wallet_id', referencedColumnName: 'id', nullable: false)]
        private(set) CreditWallet $wallet,
        /** Signed credit movement: positive = credit, negative = debit. */
        #[ORM\Column]
        private(set) int $amount,
        #[ORM\Column]
        private(set) int $balanceAfter,
        #[ORM\Column(enumType: CreditTransactionType::class)]
        private(set) CreditTransactionType $type,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private(set) ?string $note,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'performed_by_id', referencedColumnName: 'id', nullable: true)]
        private(set) ?User $performedBy,
        #[ORM\ManyToOne(targetEntity: CreditPurchase::class)]
        #[ORM\JoinColumn(name: 'purchase_id', referencedColumnName: 'id', nullable: true)]
        private(set) ?CreditPurchase $purchase,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
    }
}
