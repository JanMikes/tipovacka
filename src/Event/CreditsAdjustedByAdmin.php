<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class CreditsAdjustedByAdmin
{
    public function __construct(
        public Uuid $userId,
        public int $amount,
        public string $note,
        public Uuid $adjustedById,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
