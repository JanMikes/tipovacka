<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\OvertimeExactRule;
use App\Service\Scoring\MatchContext;
use PHPUnit\Framework\TestCase;

final class OvertimeExactRuleTest extends TestCase
{
    public function testMetadata(): void
    {
        $rule = new OvertimeExactRule();

        self::assertSame('overtime_exact', $rule->identifier);
        self::assertSame(3, $rule->defaultPoints);
        self::assertFalse($rule->enabledByDefault);
        self::assertSame('overtime', $rule->category);
    }

    public function testHitsOnExactOvertimeTip(): void
    {
        $rule = new OvertimeExactRule();

        $guess = RuleTestFactory::guessWithDetails(1, 1, null, 2, 1);
        $match = RuleTestFactory::finishedMatchWithDetails(1, 1, null, 2, 1);

        self::assertSame(1, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testHitsEvenWhenRegularDrawScoreDiffers(): void
    {
        $rule = new OvertimeExactRule();

        // Tipped 1:1 → OT 3:2; actual 2:2 → OT 3:2. The rule scores the OT
        // continuation, not the regular draw (base rules already score that).
        $guess = RuleTestFactory::guessWithDetails(1, 1, null, 3, 2);
        $match = RuleTestFactory::finishedMatchWithDetails(2, 2, null, 3, 2);

        self::assertSame(1, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWhenOvertimeTipDiffers(): void
    {
        $rule = new OvertimeExactRule();

        $guess = RuleTestFactory::guessWithDetails(1, 1, null, 2, 1);
        $match = RuleTestFactory::finishedMatchWithDetails(1, 1, null, 1, 2);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWhenMatchWasNoDraw(): void
    {
        $rule = new OvertimeExactRule();

        $guess = RuleTestFactory::guessWithDetails(1, 1, null, 2, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWhenMatchDrawWithoutOvertimeScore(): void
    {
        $rule = new OvertimeExactRule();

        $guess = RuleTestFactory::guessWithDetails(1, 1, null, 2, 1);
        $match = RuleTestFactory::finishedMatch(1, 1);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWhenGuessHasNoOvertimeTip(): void
    {
        $rule = new OvertimeExactRule();

        $guess = RuleTestFactory::guess(1, 1);
        $match = RuleTestFactory::finishedMatchWithDetails(1, 1, null, 2, 1);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroOnScheduledMatch(): void
    {
        $rule = new OvertimeExactRule();

        $guess = RuleTestFactory::guessWithDetails(1, 1, null, 2, 1);
        $match = RuleTestFactory::scheduledMatch();

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }
}
