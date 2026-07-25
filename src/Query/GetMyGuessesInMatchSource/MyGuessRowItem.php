<?php

declare(strict_types=1);

namespace App\Query\GetMyGuessesInMatchSource;

use App\Enum\SportMatchState;
use App\Value\TeamView;
use Symfony\Component\Uid\Uuid;

final readonly class MyGuessRowItem
{
    public function __construct(
        public Uuid $sportMatchId,
        public TeamView $homeTeam,
        public TeamView $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public SportMatchState $state,
        public ?int $actualHomeScore,
        public ?int $actualAwayScore,
        public int $myHomeScore,
        public int $myAwayScore,
    ) {
    }
}
