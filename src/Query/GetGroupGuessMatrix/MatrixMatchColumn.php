<?php

declare(strict_types=1);

namespace App\Query\GetGroupGuessMatrix;

use App\Enum\SportMatchState;
use Symfony\Component\Uid\Uuid;

final readonly class MatrixMatchColumn
{
    /**
     * @param list<int> $topScores distinct positive point values awarded in this column, highest first (up to 3)
     */
    public function __construct(
        public Uuid $sportMatchId,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public SportMatchState $state,
        public ?int $actualHomeScore,
        public ?int $actualAwayScore,
        public array $topScores,
    ) {
    }
}
