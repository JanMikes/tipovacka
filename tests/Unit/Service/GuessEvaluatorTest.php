<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TournamentRuleConfiguration;
use App\Repository\TournamentRuleConfigurationRepository;
use App\Rule\CorrectAwayGoalsRule;
use App\Rule\CorrectHomeGoalsRule;
use App\Rule\CorrectOutcomeRule;
use App\Rule\ExactScoreRule;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;
use App\Service\Scoring\GuessEvaluator;
use App\Tests\Unit\Rule\RuleTestFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GuessEvaluatorTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    public function testReturnsNullWhenMatchIsNotFinished(): void
    {
        $evaluator = $this->makeEvaluator([]);

        $guess = RuleTestFactory::guess(2, 1);
        $match = RuleTestFactory::scheduledMatch();

        self::assertNull($evaluator->evaluate($guess, $match, $this->now));
    }

    public function testEvaluationIncludesOnlyHitRules(): void
    {
        // Guess 3:0 vs actual 2:1 → only correct_outcome hits (home win).
        $evaluator = $this->makeEvaluator([
            'exact_score' => 5,
            'correct_outcome' => 3,
            'correct_home_goals' => 1,
            'correct_away_goals' => 1,
        ]);

        $guess = RuleTestFactory::guess(3, 0);
        $match = RuleTestFactory::finishedMatch(2, 1);

        $evaluation = $evaluator->evaluate($guess, $match, $this->now);

        self::assertNotNull($evaluation);
        self::assertSame(3, $evaluation->totalPoints);
        self::assertCount(1, $evaluation->rulePoints);

        $first = $evaluation->rulePoints->first();
        self::assertNotFalse($first);
        self::assertSame('correct_outcome', $first->ruleIdentifier);
        self::assertSame(3, $first->points);
    }

    public function testEvaluationSumsAllHitRules(): void
    {
        // Guess 2:1 vs actual 2:1 → all four rules hit.
        $evaluator = $this->makeEvaluator([
            'exact_score' => 5,
            'correct_outcome' => 3,
            'correct_home_goals' => 1,
            'correct_away_goals' => 1,
        ]);

        $guess = RuleTestFactory::guess(2, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);

        $evaluation = $evaluator->evaluate($guess, $match, $this->now);

        self::assertNotNull($evaluation);
        self::assertSame(10, $evaluation->totalPoints);
        self::assertCount(4, $evaluation->rulePoints);
    }

    public function testDisabledRuleIsIgnored(): void
    {
        // Only enable exact_score; guess 2:1 vs 2:1.
        $evaluator = $this->makeEvaluator([
            'exact_score' => 7,
        ]);

        $guess = RuleTestFactory::guess(2, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);

        $evaluation = $evaluator->evaluate($guess, $match, $this->now);

        self::assertNotNull($evaluation);
        self::assertSame(7, $evaluation->totalPoints);
        self::assertCount(1, $evaluation->rulePoints);
    }

    /**
     * @param array<string, int> $enabledRulePoints
     */
    private function makeEvaluator(array $enabledRulePoints): GuessEvaluator
    {
        $registry = new RuleRegistry([
            new ExactScoreRule(),
            new CorrectOutcomeRule(),
            new CorrectHomeGoalsRule(),
            new CorrectAwayGoalsRule(),
        ]);

        $repo = $this->createStub(TournamentRuleConfigurationRepository::class);

        $tournament = RuleTestFactory::tournament();
        $configurations = [];
        foreach ($enabledRulePoints as $identifier => $points) {
            $configurations[$identifier] = new TournamentRuleConfiguration(
                id: Uuid::v7(),
                tournament: $tournament,
                ruleIdentifier: $identifier,
                enabled: true,
                points: $points,
                now: $this->now,
            );
        }

        $repo->method('getEnabledForTournament')->willReturn($configurations);

        $identity = $this->createStub(ProvideIdentity::class);
        $identity->method('next')->willReturnCallback(fn () => Uuid::v7());

        return new GuessEvaluator($registry, $repo, $identity);
    }
}
