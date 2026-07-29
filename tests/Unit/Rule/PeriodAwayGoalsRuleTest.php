<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\PeriodAwayGoalsRule;
use App\Service\Scoring\MatchContext;
use PHPUnit\Framework\TestCase;

final class PeriodAwayGoalsRuleTest extends TestCase
{
    public function testMetadata(): void
    {
        $rule = new PeriodAwayGoalsRule();

        self::assertSame('period_away_goals', $rule->identifier);
        self::assertSame(1, $rule->defaultPoints);
        self::assertFalse($rule->enabledByDefault);
        self::assertSame('periods', $rule->category);
    }

    public function testCountsPeriodsWithCorrectAwayGoals(): void
    {
        $rule = new PeriodAwayGoalsRule();

        // Away goals right in both periods (1 and 2), home goals wrong in both.
        $guess = RuleTestFactory::guessWithDetails(0, 3, [[0, 1], [0, 2]]);
        $match = RuleTestFactory::finishedMatchWithDetails(3, 3, [[2, 1], [1, 2]]);

        self::assertSame(2, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    /**
     * The worked example behind the product decision (2026-07-29): the first half
     * ends 2:1, the player tipped 2:0 — the away goals MISS (0 ≠ 1). The second
     * period is filler that hits nothing.
     */
    public function testWorkedExampleFirstHalfTwoOneTippedTwoZero(): void
    {
        $rule = new PeriodAwayGoalsRule();

        $guess = RuleTestFactory::guessWithDetails(5, 1, [[2, 0], [3, 1]]);
        $match = RuleTestFactory::finishedMatchWithDetails(2, 1, [[2, 1], [0, 0]]);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    /** An exactly tipped period counts here too — exactly like the whole-match trio. */
    public function testExactPeriodStillCounts(): void
    {
        $rule = new PeriodAwayGoalsRule();

        // First period exactly tipped (1:0), second period wrong in both numbers.
        $guess = RuleTestFactory::guessWithDetails(3, 5, [[1, 0], [2, 5]]);
        $match = RuleTestFactory::finishedMatchWithDetails(1, 0, [[1, 0], [0, 0]]);

        self::assertSame(1, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWithoutData(): void
    {
        $rule = new PeriodAwayGoalsRule();

        self::assertSame(0, $rule->evaluate(
            RuleTestFactory::guess(2, 1),
            RuleTestFactory::finishedMatchWithDetails(2, 1, [[1, 0], [1, 1]]),
            MatchContext::empty(),
        ));

        self::assertSame(0, $rule->evaluate(
            RuleTestFactory::guessWithDetails(2, 1, [[1, 0], [1, 1]]),
            RuleTestFactory::finishedMatch(2, 1),
            MatchContext::empty(),
        ));
    }

    public function testZeroWhenAwayGoalsWrong(): void
    {
        $rule = new PeriodAwayGoalsRule();

        // Home goals right in both periods, away goals wrong in both.
        $guess = RuleTestFactory::guessWithDetails(3, 0, [[2, 0], [1, 0]]);
        $match = RuleTestFactory::finishedMatchWithDetails(3, 3, [[2, 1], [1, 2]]);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }
}
