<?php

declare(strict_types=1);

namespace App\Query\ListRecentEvaluatedGuessesForUser;

use Symfony\Component\Uid\Uuid;

final readonly class EvaluatedGuessItem
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $tournamentId,
        public string $tournamentName,
        public Uuid $groupId,
        public string $groupName,
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
