<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * At competition start every premium charge was covered — the competition stays
 * premium and is marked reconciled. Consumed by S11 notifications;
 * recording-only in S10.
 */
final readonly class PremiumConfirmed
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $ownerId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
