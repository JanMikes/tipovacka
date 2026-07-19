<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * A premium manager's wallet dipped below
 * {@see \App\Service\Credits\PricingConfig::LOW_BALANCE_WARNING_THRESHOLD} after
 * a join charge attempt. Consumed by S11 notifications (dedup per
 * competition/day lives there); recording-only in S10.
 */
final readonly class PremiumBalanceLow
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $ownerId,
        public int $balance,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
