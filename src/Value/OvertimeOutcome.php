<?php

declare(strict_types=1);

namespace App\Value;

use App\Enum\MatchSide;

/**
 * Who won after extra time or a shootout, once regular time ended in a draw.
 *
 * The ONE home of the stored convention (DOMAIN.md §Scoring, 2026-09-03): the
 * after-overtime pair is never a real score — it is the regular-time draw plus
 * ONE goal for the winner (2:2 → 3:2 or 2:3), whatever happened in the
 * prolongation and however many penalties went in. A tip reads the same way:
 * „bude to remíza 2:2 a vyhraje A".
 */
final readonly class OvertimeOutcome
{
    public function __construct(
        public MatchSide $winner,
    ) {
    }

    /**
     * The pair to store next to the given regular-time draw.
     *
     * @return array{int, int}
     */
    public function scoreAfter(int $homeScore, int $awayScore): array
    {
        return MatchSide::Home === $this->winner
            ? [$homeScore + 1, $awayScore]
            : [$homeScore, $awayScore + 1];
    }

    /**
     * Reads the winner back from a stored pair; null when the pair is not the
     * draw-plus-one shape, or regular time was not a draw at all.
     */
    public static function fromScores(int $homeScore, int $awayScore, int $overtimeHomeScore, int $overtimeAwayScore): ?self
    {
        if ($homeScore !== $awayScore) {
            return null;
        }

        if ($overtimeHomeScore === $homeScore + 1 && $overtimeAwayScore === $awayScore) {
            return new self(MatchSide::Home);
        }

        if ($overtimeHomeScore === $homeScore && $overtimeAwayScore === $awayScore + 1) {
            return new self(MatchSide::Away);
        }

        return null;
    }
}
