<?php

declare(strict_types=1);

namespace App\Query\ListUpcomingMatchesForUser;

use Symfony\Component\Uid\Uuid;

final readonly class UpcomingMatchItem
{
    public function __construct(
        public Uuid $id,
        public Uuid $tournamentId,
        public string $tournamentName,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
    ) {
    }
}
