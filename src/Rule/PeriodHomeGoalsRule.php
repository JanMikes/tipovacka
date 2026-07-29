<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Service\Scoring\MatchContext;

/**
 * Per-period counterpart of {@see CorrectHomeGoalsRule}: counts periods
 * (poločasy / třetiny) where the tipped HOME goals match the actual ones,
 * regardless of the away goals. Deliberately NOT exclusive with
 * {@see PeriodExactRule} — exactly like the whole-match trio, where an exact
 * score also earns the two per-team goal rules.
 *
 * Only periods where BOTH the guess and the match carry data participate.
 */
#[AsRule]
final class PeriodHomeGoalsRule implements Rule
{
    public const string IDENTIFIER = 'period_home_goals';

    public string $identifier { get => self::IDENTIFIER; }

    public string $label { get => 'Počet gólů domácí v části zápasu'; }

    public string $description { get => 'Body za každou část zápasu (poločas / třetinu) se správně tipnutým počtem gólů domácího týmu.'; }

    public int $defaultPoints { get => 1; }

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
            if ($guessPeriods->homeAt($index) === $matchPeriods->homeAt($index)) {
                ++$hits;
            }
        }

        return $hits;
    }
}
