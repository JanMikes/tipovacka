<?php

declare(strict_types=1);

namespace App\Query\ListUpcomingMatchesForUser;

use Symfony\Component\Uid\Uuid;

final readonly class UpcomingMatchItem
{
    public function __construct(
        public Uuid $id,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
        public ?string $round,
        public int $competitionsCount,
        public int $guessedCompetitionsCount,
        public int $pendingCompetitionsCount,
    ) {
    }
}
