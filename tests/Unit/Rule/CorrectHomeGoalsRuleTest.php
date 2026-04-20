<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\CorrectHomeGoalsRule;
use PHPUnit\Framework\TestCase;

final class CorrectHomeGoalsRuleTest extends TestCase
{
    public function testHitsOnMatchingHomeScore(): void
    {
        $rule = new CorrectHomeGoalsRule();

        $guess = RuleTestFactory::guess(2, 0);
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(1, $rule->evaluate($guess, $match));
    }

    public function testMissesOnDifferentHomeScore(): void
    {
        $rule = new CorrectHomeGoalsRule();

        $guess = RuleTestFactory::guess(3, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(0, $rule->evaluate($guess, $match));
    }

    public function testReturnsZeroWhenMatchHasNoScore(): void
    {
        $rule = new CorrectHomeGoalsRule();

        $guess = RuleTestFactory::guess(0, 0);
        $match = RuleTestFactory::scheduledMatch();

        self::assertSame(0, $rule->evaluate($guess, $match));
    }
}
