<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\CorrectAwayGoalsRule;
use PHPUnit\Framework\TestCase;

final class CorrectAwayGoalsRuleTest extends TestCase
{
    public function testHitsOnMatchingAwayScore(): void
    {
        $rule = new CorrectAwayGoalsRule();

        $guess = RuleTestFactory::guess(0, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(1, $rule->evaluate($guess, $match));
    }

    public function testMissesOnDifferentAwayScore(): void
    {
        $rule = new CorrectAwayGoalsRule();

        $guess = RuleTestFactory::guess(2, 3);
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(0, $rule->evaluate($guess, $match));
    }

    public function testReturnsZeroWhenMatchHasNoScore(): void
    {
        $rule = new CorrectAwayGoalsRule();

        $guess = RuleTestFactory::guess(0, 0);
        $match = RuleTestFactory::scheduledMatch();

        self::assertSame(0, $rule->evaluate($guess, $match));
    }
}
