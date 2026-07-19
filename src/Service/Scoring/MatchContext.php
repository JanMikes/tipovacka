<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use Symfony\Component\Uid\Uuid;

/**
 * Per-match evaluation context passed to every {@see \App\Rule\Rule}.
 *
 * Rules receive only (Guess, SportMatch, MatchContext) and never query
 * repositories — the {@see GuessEvaluator} builds this ONCE per match (the
 * finished-match handlers loop over many guesses of one match) and hands the
 * derived data to each rule. Future rule inputs (assists, cards, …) extend
 * this class instead of widening the Rule interface.
 */
final readonly class MatchContext
{
    /**
     * @param list<Uuid> $goalScorerPlayerIds distinct players with ≥1 goal MatchEvent in this match
     */
    public function __construct(
        public array $goalScorerPlayerIds = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function hasGoalBy(Uuid $playerId): bool
    {
        foreach ($this->goalScorerPlayerIds as $scorerId) {
            if ($scorerId->equals($playerId)) {
                return true;
            }
        }

        return false;
    }
}
