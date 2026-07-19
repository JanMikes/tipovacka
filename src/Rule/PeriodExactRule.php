<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Service\Scoring\MatchContext;

/**
 * Counts periods (poločasy / třetiny) where the tipped period score matches the
 * actual one EXACTLY. Only periods where BOTH the guess and the match carry
 * data participate — a match finished without period detail scores nothing here.
 */
#[AsRule]
final class PeriodExactRule implements Rule
{
    public const string IDENTIFIER = 'period_exact';

    public string $identifier { get => self::IDENTIFIER; }

    public string $label { get => 'Přesný výsledek části zápasu'; }

    public string $description { get => 'Body za každou část zápasu (poločas / třetinu) s přesně trefeným skóre.'; }

    public int $defaultPoints { get => 5; }

    public bool $enabledByDefault { get => false; }

    public string $category { get => 'periods'; }

    public function evaluate(Guess $guess, SportMatch $match, MatchContext $context): int
    {
        $guessPeriods = $guess->periodScores;
        $matchPeriods = $match->periodScores;

        if (null === $guessPeriods || null === $matchPeriods) {
            return 0;
        }

        $hits = 0;
        $comparable = min(count($guessPeriods), count($matchPeriods));

        for ($index = 0; $index < $comparable; ++$index) {
            if ($guessPeriods->homeAt($index) === $matchPeriods->homeAt($index)
                && $guessPeriods->awayAt($index) === $matchPeriods->awayAt($index)
            ) {
                ++$hits;
            }
        }

        return $hits;
    }
}
