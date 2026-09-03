<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Service\Scoring\MatchContext;

/**
 * Binary: 1 iff the match's regular result was a draw decided after extra
 * time / a shootout, the guess carries a winner pick (which implies the
 * guess's main tip was a draw — entity invariant) and it names the same
 * winner. The exact draw score is NOT compared here — base rules score it —
 * so „1:1 a vyhraje A" hits a 2:2 won by A. The stored pair is the draw plus
 * one goal for the winner (Value\OvertimeOutcome), never a real score.
 */
#[AsRule]
final class OvertimeExactRule implements Rule
{
    public const string IDENTIFIER = 'overtime_exact';

    public string $identifier { get => self::IDENTIFIER; }

    public string $label { get => 'Vítěz po prodloužení / penaltách'; }

    public string $description { get => 'Uhádnutý vítěz po prodloužení či penaltách, když zápas skončil v základní hrací době remízou.'; }

    public int $defaultPoints { get => 3; }

    public bool $enabledByDefault { get => false; }

    public string $category { get => 'overtime'; }

    public function evaluate(Guess $guess, SportMatch $match, MatchContext $context): int
    {
        // Null unless the match was a regular-time draw with a stored winner.
        $winner = $match->overtimeWinner;

        if (null === $winner) {
            return 0;
        }

        return $guess->overtimeWinner === $winner ? 1 : 0;
    }
}
