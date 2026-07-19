<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\PeriodExactRule;
use App\Service\Scoring\MatchContext;
use PHPUnit\Framework\TestCase;

final class PeriodExactRuleTest extends TestCase
{
    public function testMetadata(): void
    {
        $rule = new PeriodExactRule();

        self::assertSame('period_exact', $rule->identifier);
        self::assertSame(5, $rule->defaultPoints);
        self::assertFalse($rule->enabledByDefault);
        self::assertSame('periods', $rule->category);
    }

    public function testCountsExactlyMatchedPeriods(): void
    {
        $rule = new PeriodExactRule();

        $guess = RuleTestFactory::guessWithDetails(2, 1, [[1, 0], [1, 1]]);
        $match = RuleTestFactory::finishedMatchWithDetails(2, 1, [[1, 0], [1, 1]]);

        self::assertSame(2, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testCountsOnlyExactPeriodsNotTendencyOnes(): void
    {
        $rule = new PeriodExactRule();

        // First period exact (1:0), second period 1:2 tipped vs 2:2 actual (miss).
        $guess = RuleTestFactory::guessWithDetails(2, 2, [[1, 0], [1, 2]]);
        $match = RuleTestFactory::finishedMatchWithDetails(3, 2, [[1, 0], [2, 2]]);

        self::assertSame(1, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWhenGuessHasNoPeriods(): void
    {
        $rule = new PeriodExactRule();

        $guess = RuleTestFactory::guess(2, 1);
        $match = RuleTestFactory::finishedMatchWithDetails(2, 1, [[1, 0], [1, 1]]);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWhenMatchHasNoPeriods(): void
    {
        $rule = new PeriodExactRule();

        $guess = RuleTestFactory::guessWithDetails(2, 1, [[1, 0], [1, 1]]);
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWhenNoPeriodMatchesExactly(): void
    {
        $rule = new PeriodExactRule();

        $guess = RuleTestFactory::guessWithDetails(2, 1, [[2, 0], [0, 1]]);
        $match = RuleTestFactory::finishedMatchWithDetails(2, 1, [[1, 0], [1, 1]]);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }
}
