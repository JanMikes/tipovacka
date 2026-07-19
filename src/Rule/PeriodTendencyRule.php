<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Service\Scoring\MatchContext;

/**
 * Counts periods with the correct 1/X/2 tendency that are NOT exact hits —
 * exclusive with {@see PeriodExactRule} per period (an exactly tipped period
 * scores only the exact rule). Only periods where both sides carry data count.
 */
#[AsRule]
final class PeriodTendencyRule implements Rule
{
    public const string IDENTIFIER = 'period_tendency';

    public string $identifier { get => self::IDENTIFIER; }

    public string $label { get => 'Tendence části zápasu'; }

    public string $description { get => 'Body za každou část zápasu se správně tipnutým vítězem či remízou (bez přesného skóre).'; }

    public int $defaultPoints { get => 2; }

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
            $guessHome = $guessPeriods->homeAt($index);
            $guessAway = $guessPeriods->awayAt($index);
            $matchHome = $matchPeriods->homeAt($index);
            $matchAway = $matchPeriods->awayAt($index);

            $isExact = $guessHome === $matchHome && $guessAway === $matchAway;
            $sameTendency = ($guessHome <=> $guessAway) === ($matchHome <=> $matchAway);

            if ($sameTendency && !$isExact) {
                ++$hits;
            }
        }

        return $hits;
    }
}
