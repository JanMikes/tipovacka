<?php

declare(strict_types=1);

namespace App\Query\ListMatchSourceSportMatches;

use App\Enum\MatchSide;
use App\Enum\SportMatchState;
use App\Value\PeriodScores;
use App\Value\TeamView;
use Symfony\Component\Uid\Uuid;

final readonly class SportMatchListItem
{
    public function __construct(
        public Uuid $id,
        public Uuid $matchSourceId,
        public TeamView $homeTeam,
        public TeamView $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
        public ?string $round,
        public bool $isPlayoff,
        public SportMatchState $state,
        public ?int $homeScore,
        public ?int $awayScore,
        public ?PeriodScores $periodScores = null,
        public ?int $overtimeHomeScore = null,
        public ?int $overtimeAwayScore = null,
        // Computed by the query (hooked properties cannot be readonly): who won
        // after extra time / penalties. Templates print THIS, never the pair.
        public ?MatchSide $overtimeWinner = null,
    ) {
    }
}
