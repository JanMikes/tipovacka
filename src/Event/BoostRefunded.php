<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * A player's active boost purchase was refunded — happens when the manager
 * re-enables premium (the competition switches to charge-the-whole-group, so
 * individual boosts are credited back). Consumed by S11 notifications;
 * recording-only in S10. See .docs/DOMAIN.md §Monetization.
 */
final readonly class BoostRefunded
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $userId,
        public string $boostType,
        public int $amount,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
