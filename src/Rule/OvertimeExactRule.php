<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Service\Scoring\MatchContext;

/**
 * Binary: 1 iff the match's regular result was a draw, the match has an
 * after-overtime score, the guess carries an overtime tip (which implies the
 * guess's main tip was a draw — entity invariant) and that tip matches the
 * match's overtime score exactly. The regular-time score stays the primary
 * evaluated result for all other rules.
 */
#[AsRule]
final class OvertimeExactRule implements Rule
{
    public const string IDENTIFIER = 'overtime_exact';

    public string $identifier { get => self::IDENTIFIER; }

    public string $label { get => 'Přesný výsledek po prodloužení'; }

    public string $description { get => 'Uhádnutý konečný stav po prodloužení či nájezdech, když zápas skončil v základní hrací době remízou.'; }

    public int $defaultPoints { get => 3; }

    public bool $enabledByDefault { get => false; }

    public string $category { get => 'overtime'; }

    public function evaluate(Guess $guess, SportMatch $match, MatchContext $context): int
    {
        if (null === $match->homeScore || null === $match->awayScore) {
            return 0;
        }

        if ($match->homeScore !== $match->awayScore || !$match->hasOvertimeScore) {
            return 0;
        }

        if (!$guess->hasOvertimeTip) {
            return 0;
        }

        return $guess->overtimeHomeScore === $match->overtimeHomeScore
            && $guess->overtimeAwayScore === $match->overtimeAwayScore
            ? 1
            : 0;
    }
}
