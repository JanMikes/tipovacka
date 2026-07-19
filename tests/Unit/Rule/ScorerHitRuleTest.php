<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rule;

use App\Rule\ScorerHitRule;
use App\Service\Scoring\MatchContext;
use PHPUnit\Framework\TestCase;

final class ScorerHitRuleTest extends TestCase
{
    public function testMetadata(): void
    {
        $rule = new ScorerHitRule();

        self::assertSame('scorer_hit', $rule->identifier);
        self::assertSame('Trefený střelec', $rule->label);
        self::assertSame(2, $rule->defaultPoints);
        self::assertFalse($rule->enabledByDefault);
        self::assertSame('scorers', $rule->category);
    }

    public function testCountsEachCorrectlyGuessedScorerOnce(): void
    {
        $rule = new ScorerHitRule();

        $scorerA = RuleTestFactory::player('Jan Novák');
        $scorerB = RuleTestFactory::player('Petr Svoboda');
        $missed = RuleTestFactory::player('Marek Doležal');

        $guess = RuleTestFactory::withScorerTips(
            RuleTestFactory::guess(2, 1),
            [$scorerA, $scorerB, $missed],
        );
        $match = RuleTestFactory::finishedMatch(2, 1);

        // Both tipped scorers scored (each counted once regardless of goal count).
        $context = RuleTestFactory::contextWithGoals([$scorerA, $scorerB]);

        self::assertSame(2, $rule->evaluate($guess, $match, $context));
    }

    public function testZeroWithoutScorerTips(): void
    {
        $rule = new ScorerHitRule();

        $guess = RuleTestFactory::guess(2, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);
        $context = RuleTestFactory::contextWithGoals([RuleTestFactory::player('Jan Novák')]);

        self::assertSame(0, $rule->evaluate($guess, $match, $context));
    }

    public function testZeroWhenMatchHasNoGoalEvents(): void
    {
        $rule = new ScorerHitRule();

        $guess = RuleTestFactory::withScorerTips(
            RuleTestFactory::guess(2, 1),
            [RuleTestFactory::player('Jan Novák')],
        );
        $match = RuleTestFactory::finishedMatch(2, 1);

        self::assertSame(0, $rule->evaluate($guess, $match, MatchContext::empty()));
    }

    public function testZeroWhenTippedScorersDidNotScore(): void
    {
        $rule = new ScorerHitRule();

        $guess = RuleTestFactory::withScorerTips(
            RuleTestFactory::guess(2, 1),
            [RuleTestFactory::player('Jan Novák')],
        );
        $match = RuleTestFactory::finishedMatch(2, 1);
        $context = RuleTestFactory::contextWithGoals([RuleTestFactory::player('Petr Svoboda')]);

        self::assertSame(0, $rule->evaluate($guess, $match, $context));
    }
}
