<?php

declare(strict_types=1);

namespace App\Command\SubmitGuessOnBehalf;

use App\Value\GuessScorerInput;
use App\Value\PeriodScores;
use Symfony\Component\Uid\Uuid;

final readonly class SubmitGuessOnBehalfCommand
{
    /**
     * @param list<GuessScorerInput> $scorers
     */
    public function __construct(
        public Uuid $actingUserId,
        public Uuid $targetUserId,
        public Uuid $competitionId,
        public Uuid $sportMatchId,
        public int $homeScore,
        public int $awayScore,
        public ?PeriodScores $periodScores = null,
        public ?int $overtimeHomeScore = null,
        public ?int $overtimeAwayScore = null,
        public array $scorers = [],
    ) {
    }
}
