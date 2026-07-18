<?php

declare(strict_types=1);

namespace App\Command\UpdateLiveScore;

use App\Value\MatchEventInput;
use App\Value\PeriodScores;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateLiveScoreCommand
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
        public array $events = [],
    ) {
    }
}
