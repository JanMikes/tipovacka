<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * At competition start at least one premium charge was uncovered — every charged
 * player was refunded and the competition was downgraded to `boosts`. Consumed
 * by S11 notifications; recording-only in S10.
 */
final readonly class PremiumDowngraded
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $ownerId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
