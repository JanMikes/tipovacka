<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\PeriodTendencyRule;
use App\Service\Scoring\MatchContext;
use PHPUnit\Framework\TestCase;

final class PeriodTendencyRuleTest extends TestCase
{
    public function testMetadata(): void
    {
        $rule = new PeriodTendencyRule();

        self::assertSame('period_tendency', $rule->identifier);
        self::assertSame(2, $rule->defaultPoints);
        self::assertFalse($rule->enabledByDefault);
        self::assertSame('periods', $rule->category);
    }

    public function testCountsCorrectTendencyPeriods(): void
    {
        $rule = new PeriodTendencyRule();

        // Both periods correct tendency, neither exact: 2:0 vs 1:0 (home win),
        // 0:2 vs 1:2 (away win).
        $guess = RuleTestFactory::guessWithDetails(2, 2, [[2, 0], [0, 2]]);
        $match = RuleTestFactory::finishedMatchWithDetails(2, 2, [[1, 0], [1, 2]]);

        self::assertSame(2, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testExactPeriodIsExcluded(): void
    {
        $rule = new PeriodTendencyRule();

        // First period EXACT (1:0 — belongs to period_exact, not here),
        // second period tendency only (0:1 tipped vs 1:2 actual — away win).
        $guess = RuleTestFactory::guessWithDetails(1, 1, [[1, 0], [0, 1]]);
        $match = RuleTestFactory::finishedMatchWithDetails(2, 2, [[1, 0], [1, 2]]);

        self::assertSame(1, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testDrawTendencyCounts(): void
    {
        $rule = new PeriodTendencyRule();

        // Tipped 1:1 draws, actual 0:0 and 2:2 — correct draw tendency, not exact.
        $guess = RuleTestFactory::guessWithDetails(2, 2, [[1, 1], [1, 1]]);
        $match = RuleTestFactory::finishedMatchWithDetails(2, 2, [[0, 0], [2, 2]]);

        self::assertSame(2, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWithoutData(): void
    {
        $rule = new PeriodTendencyRule();

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

    public function testZeroWhenTendencyWrong(): void
    {
        $rule = new PeriodTendencyRule();

        // Tipped home wins, actual away wins / draw.
        $guess = RuleTestFactory::guessWithDetails(4, 0, [[2, 0], [2, 0]]);
        $match = RuleTestFactory::finishedMatchWithDetails(1, 2, [[0, 1], [1, 1]]);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }
}
