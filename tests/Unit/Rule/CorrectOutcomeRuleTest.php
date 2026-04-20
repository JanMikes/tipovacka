<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\CorrectOutcomeRule;
use PHPUnit\Framework\TestCase;

final class CorrectOutcomeRuleTest extends TestCase
{
    public function testHitsWhenBothAreHomeWins(): void
    {
        $rule = new CorrectOutcomeRule();

        $guess = RuleTestFactory::guess(3, 0);
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(1, $rule->evaluate($guess, $match));
    }

    public function testHitsOnDraw(): void
    {
        $rule = new CorrectOutcomeRule();

        $guess = RuleTestFactory::guess(1, 1);
        $match = RuleTestFactory::finishedMatch(2, 2);

        self::assertSame(1, $rule->evaluate($guess, $match));
    }

    public function testHitsOnAwayWin(): void
    {
        $rule = new CorrectOutcomeRule();

        $guess = RuleTestFactory::guess(0, 1);
        $match = RuleTestFactory::finishedMatch(1, 3);

        self::assertSame(1, $rule->evaluate($guess, $match));
    }

    public function testMissesOnDifferentOutcome(): void
    {
        $rule = new CorrectOutcomeRule();

        $guess = RuleTestFactory::guess(2, 0);
        $match = RuleTestFactory::finishedMatch(1, 2);

        self::assertSame(0, $rule->evaluate($guess, $match));
    }

    public function testReturnsZeroWhenMatchHasNoScore(): void
    {
        $rule = new CorrectOutcomeRule();

        $guess = RuleTestFactory::guess(1, 1);
        $match = RuleTestFactory::scheduledMatch();

        self::assertSame(0, $rule->evaluate($guess, $match));
    }
}
