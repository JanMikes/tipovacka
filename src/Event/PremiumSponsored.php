<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * An admin granted a competition premium at our expense: it is premium from now
 * on and nobody — organizer or member — is ever charged for it.
 */
final readonly class PremiumSponsored
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $ownerId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
