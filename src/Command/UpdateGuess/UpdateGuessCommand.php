<?php

declare(strict_types=1);

namespace App\Command\UpdateGuess;

use App\Value\GuessScorerInput;
use App\Value\PeriodScores;
use Symfony\Component\Uid\Uuid;

/**
 * Full-replace semantics: the command carries the COMPLETE tip (main score,
 * periods, overtime, scorers) — omitted parts are cleared. Call sites with a
 * partial UI pass the untouched parts through from the existing guess.
 */
final readonly class UpdateGuessCommand
{
    /**
     * @param list<GuessScorerInput> $scorers
     */
    public function __construct(
        public Uuid $userId,
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
