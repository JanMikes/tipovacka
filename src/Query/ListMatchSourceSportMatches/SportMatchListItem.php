<?php

declare(strict_types=1);

namespace App\Query\ListMatchSourceSportMatches;

use App\Enum\SportMatchState;
use Symfony\Component\Uid\Uuid;

final readonly class SportMatchListItem
{
    public function __construct(
        public Uuid $id,
        public Uuid $matchSourceId,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
        public ?string $round,
        public bool $isPlayoff,
        public SportMatchState $state,
        public ?int $homeScore,
        public ?int $awayScore,
    ) {
    }
}
