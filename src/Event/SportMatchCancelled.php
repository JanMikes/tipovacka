<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class SportMatchCancelled
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $tournamentId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
