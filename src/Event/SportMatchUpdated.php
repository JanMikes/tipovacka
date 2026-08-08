<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * A match's details changed. `previousKickoffAt` is set ONLY when the kickoff
 * itself moved — it is what lets {@see RepinOwnKickoffDeadlinesHandler} tell an
 * override that meant „until this match's own kickoff" from one a manager
 * deliberately set to some other moment.
 */
final readonly class SportMatchUpdated
{
    public function __construct(
        public Uuid $sportMatchId,
        public \DateTimeImmutable $occurredOn,
        public ?\DateTimeImmutable $previousKickoffAt = null,
    ) {
    }
}
