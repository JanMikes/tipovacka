<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionGuessMatrix;

use App\Enum\SportMatchState;
use App\Value\TeamView;
use Symfony\Component\Uid\Uuid;

final readonly class MatrixMatchColumn
{
    /**
     * @param list<int> $topScores distinct positive point values awarded in this column, highest first (up to 3)
     */
    public function __construct(
        public Uuid $sportMatchId,
        public TeamView $homeTeam,
        public TeamView $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public SportMatchState $state,
        public ?int $actualHomeScore,
        public ?int $actualAwayScore,
        public array $topScores,
    ) {
    }
}
