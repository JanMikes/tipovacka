<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CreditPurchaseStatus;
use App\Event\CreditsPurchased;
use App\Exception\CreditPurchaseNotPending;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'credit_purchases')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'IDX_credit_purchases_user_created')]
#[ORM\UniqueConstraint(name: 'UIDX_credit_purchases_session', columns: ['stripe_checkout_session_id'])]
class CreditPurchase implements EntityWithEvents
{
    use HasEvents;

    #[ORM\Column(enumType: CreditPurchaseStatus::class)]
    public private(set) CreditPurchaseStatus $status = CreditPurchaseStatus::Pending;

    #[ORM\Column(nullable: true)]
    public private(set) ?string $stripePaymentIntentId = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?string $stripeInvoiceId = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?string $stripeInvoiceUrl = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?string $stripeInvoicePdfUrl = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public bool $isPending {
        get => CreditPurchaseStatus::Pending === $this->status;
    }

    public bool $isCompleted {
        get => CreditPurchaseStatus::Completed === $this->status;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $user,
        #[ORM\Column]
        private(set) int $credits,
        /** Total price in minor units (haléře) — 1 credit = 1 CZK = 100 haléřů. */
        #[ORM\Column]
        private(set) int $amountTotal,
        #[ORM\Column(length: 3)]
        private(set) string $currency,
        #[ORM\Column(length: 255)]
        private(set) string $stripeCheckoutSessionId,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $this->createdAt;
    }

    public function markCompleted(?string $stripePaymentIntentId, \DateTimeImmutable $now): void
    {
        if (!$this->isPending) {
            throw CreditPurchaseNotPending::withStatus($this->id, $this->status);
        }

        $this->status = CreditPurchaseStatus::Completed;
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        $this->completedAt = $now;
        $this->updatedAt = $now;

        $this->recordThat(new CreditsPurchased(
            userId: $this->user->id,
            purchaseId: $this->id,
            credits: $this->credits,
            occurredOn: $now,
        ));
    }

    public function markExpired(\DateTimeImmutable $now): void
    {
        if (!$this->isPending) {
            throw CreditPurchaseNotPending::withStatus($this->id, $this->status);
        }

        $this->status = CreditPurchaseStatus::Expired;
        $this->updatedAt = $now;
    }

    public function markFailed(\DateTimeImmutable $now): void
    {
        if (!$this->isPending) {
            throw CreditPurchaseNotPending::withStatus($this->id, $this->status);
        }

        $this->status = CreditPurchaseStatus::Failed;
        $this->updatedAt = $now;
    }

    public function attachInvoice(string $stripeInvoiceId, ?string $stripeInvoiceUrl, ?string $stripeInvoicePdfUrl, \DateTimeImmutable $now): void
    {
        $this->stripeInvoiceId = $stripeInvoiceId;
        $this->stripeInvoiceUrl = $stripeInvoiceUrl;
        $this->stripeInvoicePdfUrl = $stripeInvoicePdfUrl;
        $this->updatedAt = $now;
    }
}
