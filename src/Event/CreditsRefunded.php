<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\CreditTransactionType;
use Symfony\Component\Uid\Uuid;

final readonly class CreditsRefunded
{
    public function __construct(
        public Uuid $walletUserId,
        public int $amount,
        public CreditTransactionType $type,
        public ?Uuid $competitionId,
        public ?Uuid $relatedUserId,
        public ?string $boostType,
        public int $balanceAfter,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
