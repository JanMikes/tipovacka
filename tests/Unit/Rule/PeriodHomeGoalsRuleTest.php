<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\PeriodAwayGoalsRule;
use App\Rule\PeriodExactRule;
use App\Rule\PeriodHomeGoalsRule;
use App\Rule\PeriodTendencyRule;
use App\Service\Scoring\MatchContext;
use PHPUnit\Framework\TestCase;

final class PeriodHomeGoalsRuleTest extends TestCase
{
    public function testMetadata(): void
    {
        $rule = new PeriodHomeGoalsRule();

        self::assertSame('period_home_goals', $rule->identifier);
        self::assertSame(1, $rule->defaultPoints);
        self::assertFalse($rule->enabledByDefault);
        self::assertSame('periods', $rule->category);
    }

    public function testCountsPeriodsWithCorrectHomeGoals(): void
    {
        $rule = new PeriodHomeGoalsRule();

        // Home goals right in both periods (2 and 1), away goals wrong in both.
        $guess = RuleTestFactory::guessWithDetails(3, 0, [[2, 0], [1, 0]]);
        $match = RuleTestFactory::finishedMatchWithDetails(3, 3, [[2, 1], [1, 2]]);

        self::assertSame(2, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    /**
     * The worked example behind the product decision (2026-07-29): the first half
     * ends 2:1, the player tipped 2:0 — per-period home goals HIT, away goals miss,
     * `period_exact` misses, `period_tendency` hits. The second period is filler
     * that hits none of the four rules, so each count below is the first half's.
     */
    public function testWorkedExampleFirstHalfTwoOneTippedTwoZero(): void
    {
        $guess = RuleTestFactory::guessWithDetails(5, 1, [[2, 0], [3, 1]]);
        $match = RuleTestFactory::finishedMatchWithDetails(2, 1, [[2, 1], [0, 0]]);
        $context = MatchContext::empty();

        self::assertSame(1, (new PeriodHomeGoalsRule())->evaluate($guess, $match, $context));
        self::assertSame(0, (new PeriodAwayGoalsRule())->evaluate($guess, $match, $context));
        self::assertSame(0, (new PeriodExactRule())->evaluate($guess, $match, $context));
        self::assertSame(1, (new PeriodTendencyRule())->evaluate($guess, $match, $context));
    }

    /** An exactly tipped period counts here too — exactly like the whole-match trio. */
    public function testExactPeriodStillCounts(): void
    {
        $rule = new PeriodHomeGoalsRule();

        // First period exactly tipped (1:0), second period wrong in both numbers.
        $guess = RuleTestFactory::guessWithDetails(3, 5, [[1, 0], [2, 5]]);
        $match = RuleTestFactory::finishedMatchWithDetails(1, 0, [[1, 0], [0, 0]]);

        self::assertSame(1, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWithoutData(): void
    {
        $rule = new PeriodHomeGoalsRule();

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

    public function testZeroWhenHomeGoalsWrong(): void
    {
        $rule = new PeriodHomeGoalsRule();

        // Away goals right in both periods, home goals wrong in both.
        $guess = RuleTestFactory::guessWithDetails(0, 3, [[0, 1], [0, 2]]);
        $match = RuleTestFactory::finishedMatchWithDetails(3, 3, [[2, 1], [1, 2]]);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }
}
