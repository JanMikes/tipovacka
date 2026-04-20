<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class SportMatchCreated
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $tournamentId,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
