<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\ExactScoreRule;
use PHPUnit\Framework\TestCase;

final class ExactScoreRuleTest extends TestCase
{
    public function testIdentifierAndLabel(): void
    {
        $rule = new ExactScoreRule();

        self::assertSame('exact_score', $rule->identifier);
        self::assertSame('Přesný výsledek', $rule->label);
        self::assertSame(5, $rule->defaultPoints);
    }

    public function testHitsOnExactMatch(): void
    {
        $rule = new ExactScoreRule();

        $guess = RuleTestFactory::guess(2, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(1, $rule->evaluate($guess, $match));
    }

    public function testMissesOnDifferentScore(): void
    {
        $rule = new ExactScoreRule();

        $guess = RuleTestFactory::guess(3, 0);
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(0, $rule->evaluate($guess, $match));
    }

    public function testReturnsZeroWhenMatchHasNoScore(): void
    {
        $rule = new ExactScoreRule();

        $guess = RuleTestFactory::guess(0, 0);
        $match = RuleTestFactory::scheduledMatch();

        self::assertSame(0, $rule->evaluate($guess, $match));
    }
}
