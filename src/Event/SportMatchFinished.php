<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class SportMatchFinished
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $tournamentId,
        public int $homeScore,
        public int $awayScore,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
