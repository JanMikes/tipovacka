<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class CreditsPurchased
{
    public function __construct(
        public Uuid $userId,
        public Uuid $purchaseId,
        public int $credits,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
