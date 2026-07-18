<?php

declare(strict_types=1);

namespace App\Command\SetSportMatchFinalScore;

use App\Value\MatchEventInput;
use App\Value\PeriodScores;
use Symfony\Component\Uid\Uuid;

final readonly class SetSportMatchFinalScoreCommand
{
    /**
     * @param list<MatchEventInput> $events
     */
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $editorId,
        public int $homeScore,
        public int $awayScore,
        public ?PeriodScores $periodScores = null,
        public ?int $overtimeHomeScore = null,
        public ?int $overtimeAwayScore = null,
        public array $events = [],
        /** „Toto byl poslední zápas zdroje" — completes the source after saving. */
        public bool $isLastMatch = false,
    ) {
    }
}
