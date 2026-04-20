<?php

declare(strict_types=1);

namespace App\Query\GetMyGuessesInTournament;

use App\Enum\SportMatchState;
use Symfony\Component\Uid\Uuid;

final readonly class MyGuessRowItem
{
    public function __construct(
        public Uuid $sportMatchId,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public SportMatchState $state,
        public ?int $actualHomeScore,
        public ?int $actualAwayScore,
        public int $myHomeScore,
        public int $myAwayScore,
    ) {
    }
}
