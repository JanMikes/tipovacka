<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * A premium per-player charge could not be covered by the manager's wallet at
 * join time (or during a later settle attempt). The member joined regardless.
 * Consumed by S11 notifications; recording-only in S10.
 */
final readonly class PremiumChargeUncovered
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $ownerId,
        public Uuid $memberId,
        public int $amount,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
