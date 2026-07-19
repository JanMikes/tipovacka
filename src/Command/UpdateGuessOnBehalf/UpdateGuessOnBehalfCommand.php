<?php

declare(strict_types=1);

namespace App\Command\UpdateGuessOnBehalf;

use App\Value\GuessScorerInput;
use App\Value\PeriodScores;
use Symfony\Component\Uid\Uuid;

/**
 * Full-replace semantics — see UpdateGuessCommand. Call sites with a partial
 * UI (batch pages, the single on-behalf form) pass untouched parts through.
 */
final readonly class UpdateGuessOnBehalfCommand
{
    /**
     * @param list<GuessScorerInput> $scorers
     */
    public function __construct(
        public Uuid $actingUserId,
        public Uuid $guessId,
        public int $homeScore,
        public int $awayScore,
        public ?PeriodScores $periodScores = null,
        public ?int $overtimeHomeScore = null,
        public ?int $overtimeAwayScore = null,
        public array $scorers = [],
    ) {
    }
}
