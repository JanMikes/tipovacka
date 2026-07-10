<?php

declare(strict_types=1);

namespace App\Query\ListCreditPurchases;

use App\Enum\CreditPurchaseStatus;
use Symfony\Component\Uid\Uuid;

final readonly class CreditPurchaseItem
{
    public function __construct(
        public Uuid $id,
        public Uuid $userId,
        public string $userDisplayName,
        public ?string $userEmail,
        public int $credits,
        public int $amountTotal,
        public string $currency,
        public CreditPurchaseStatus $status,
        public string $stripeCheckoutSessionId,
        public ?string $stripePaymentIntentId,
        public ?string $invoiceUrl,
        public ?string $invoicePdfUrl,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $completedAt,
    ) {
    }
}
