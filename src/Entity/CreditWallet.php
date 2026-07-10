<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CreditTransactionType;
use App\Event\CreditsAdjustedByAdmin;
use App\Exception\InsufficientCredits;
use App\Exception\InvalidCreditAmount;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Per-user credit balance. The balance is only ever changed together with an
 * immutable CreditTransaction ledger entry (see credit methods below), so the
 * ledger always reconciles with the balance. 1 credit = 1 CZK.
 */
#[ORM\Entity]
#[ORM\Table(name: 'credit_wallets')]
class CreditWallet implements EntityWithEvents
{
    use HasEvents;

    #[ORM\Column]
    public private(set) int $balance = 0;

    #[ORM\Column(nullable: true)]
    public private(set) ?string $stripeCustomerId = null;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\OneToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, unique: true)]
        private(set) User $user,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $this->createdAt;
    }

    public function assignStripeCustomerId(string $stripeCustomerId, \DateTimeImmutable $now): void
    {
        $this->stripeCustomerId = $stripeCustomerId;
        $this->updatedAt = $now;
    }

    public function creditFromPurchase(Uuid $transactionId, CreditPurchase $purchase, \DateTimeImmutable $now): CreditTransaction
    {
        $this->balance += $purchase->credits;
        $this->updatedAt = $now;

        return new CreditTransaction(
            id: $transactionId,
            wallet: $this,
            amount: $purchase->credits,
            balanceAfter: $this->balance,
            type: CreditTransactionType::Purchase,
            note: null,
            performedBy: null,
            purchase: $purchase,
            createdAt: $now,
        );
    }

    public function adjustByAdmin(Uuid $transactionId, int $amount, string $note, User $adjustedBy, \DateTimeImmutable $now): CreditTransaction
    {
        if (0 === $amount) {
            throw InvalidCreditAmount::zeroAdjustment();
        }

        if ($this->balance + $amount < 0) {
            throw InsufficientCredits::forAdjustment($this->balance, $amount);
        }

        $this->balance += $amount;
        $this->updatedAt = $now;

        $this->recordThat(new CreditsAdjustedByAdmin(
            userId: $this->user->id,
            amount: $amount,
            note: $note,
            adjustedById: $adjustedBy->id,
            occurredOn: $now,
        ));

        return new CreditTransaction(
            id: $transactionId,
            wallet: $this,
            amount: $amount,
            balanceAfter: $this->balance,
            type: CreditTransactionType::AdminAdjustment,
            note: $note,
            performedBy: $adjustedBy,
            purchase: null,
            createdAt: $now,
        );
    }
}
