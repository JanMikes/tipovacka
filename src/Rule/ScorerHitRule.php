<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Service\Scoring\MatchContext;

/**
 * Counts guessed players (GuessScorer rows) with at least one goal MatchEvent
 * in this match — each correct player counts once, no matter how many goals
 * they scored. The evaluator multiplies the count by the configured points.
 */
#[AsRule]
final class ScorerHitRule implements Rule
{
    public const string IDENTIFIER = 'scorer_hit';

    public string $identifier { get => self::IDENTIFIER; }

    public string $label { get => 'Trefený střelec'; }

    public string $description { get => 'Body za každého správně tipnutého střelce zápasu.'; }

    public int $defaultPoints { get => 2; }

    public bool $enabledByDefault { get => false; }

    public string $category { get => 'scorers'; }

    public function evaluate(Guess $guess, SportMatch $match, MatchContext $context): int
    {
        $hits = 0;

        foreach ($guess->scorers as $scorer) {
            if ($context->hasGoalBy($scorer->player->id)) {
                ++$hits;
            }
        }

        return $hits;
    }
}
