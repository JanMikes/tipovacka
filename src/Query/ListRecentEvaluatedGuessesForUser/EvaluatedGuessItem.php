<?php

declare(strict_types=1);

namespace App\Query\ListRecentEvaluatedGuessesForUser;

use Symfony\Component\Uid\Uuid;

final readonly class EvaluatedGuessItem
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public Uuid $competitionId,
        public string $competitionName,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public int $actualHomeScore,
        public int $actualAwayScore,
        public int $myHomeScore,
        public int $myAwayScore,
        public int $totalPoints,
    ) {
    }
}
