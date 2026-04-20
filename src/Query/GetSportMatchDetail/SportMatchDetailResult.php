<?php

declare(strict_types=1);

namespace App\Query\GetSportMatchDetail;

use App\Enum\SportMatchState;
use Symfony\Component\Uid\Uuid;

final readonly class SportMatchDetailResult
{
    public function __construct(
        public Uuid $id,
        public Uuid $tournamentId,
        public string $tournamentName,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
        public SportMatchState $state,
        public ?int $homeScore,
        public ?int $awayScore,
        public bool $isOpenForGuesses,
    ) {
    }
}
