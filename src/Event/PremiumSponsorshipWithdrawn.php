<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * The gift ended: the competition keeps the premium it has, but from here it is
 * billed to its organizer like any other — the next member to join is charged.
 */
final readonly class PremiumSponsorshipWithdrawn
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $ownerId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
