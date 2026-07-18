<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CompetitionRuleConfiguration;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Repository\CompetitionRuleConfigurationRepository;
use App\Rule\CorrectAwayGoalsRule;
use App\Rule\CorrectHomeGoalsRule;
use App\Rule\CorrectOutcomeRule;
use App\Rule\ExactScoreRule;
use App\Rule\Rule;
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
            'exact_score' => [true, 5],
            'correct_outcome' => [true, 3],
            'correct_home_goals' => [true, 1],
            'correct_away_goals' => [true, 1],
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
            'exact_score' => [true, 5],
            'correct_outcome' => [true, 3],
            'correct_home_goals' => [true, 1],
            'correct_away_goals' => [true, 1],
        ]);

        $guess = RuleTestFactory::guess(2, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);

        $evaluation = $evaluator->evaluate($guess, $match, $this->now);

        self::assertNotNull($evaluation);
        self::assertSame(10, $evaluation->totalPoints);
        self::assertCount(4, $evaluation->rulePoints);
    }

    public function testStoredDisabledRowWinsOverEnabledByDefault(): void
    {
        // All four base rules have enabledByDefault=true, but three are stored disabled.
        $evaluator = $this->makeEvaluator([
            'exact_score' => [true, 7],
            'correct_outcome' => [false, 3],
            'correct_home_goals' => [false, 1],
            'correct_away_goals' => [false, 1],
        ]);

        $guess = RuleTestFactory::guess(2, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);

        $evaluation = $evaluator->evaluate($guess, $match, $this->now);

        self::assertNotNull($evaluation);
        self::assertSame(7, $evaluation->totalPoints);
        self::assertCount(1, $evaluation->rulePoints);
    }

    public function testMissingRowFallsBackToDefaultsWhenEnabledByDefault(): void
    {
        // NO stored rows at all — every base rule (enabledByDefault=true) still
        // participates with its defaultPoints. Display query and evaluator agree.
        $evaluator = $this->makeEvaluator([]);

        $guess = RuleTestFactory::guess(2, 1);
        $match = RuleTestFactory::finishedMatch(2, 1);

        $evaluation = $evaluator->evaluate($guess, $match, $this->now);

        self::assertNotNull($evaluation);
        self::assertSame(10, $evaluation->totalPoints); // 5 + 3 + 1 + 1 defaults
        self::assertCount(4, $evaluation->rulePoints);
    }

    public function testMissingRowWithEnabledByDefaultFalseDoesNotParticipate(): void
    {
        // Future-optional-rule semantics: registered, always hits, but
        // enabledByDefault=false and no stored row → contributes nothing.
        $evaluator = $this->makeEvaluator([], [$this->optionalRule()]);

        $guess = RuleTestFactory::guess(3, 0);
        $match = RuleTestFactory::finishedMatch(2, 1);

        $evaluation = $evaluator->evaluate($guess, $match, $this->now);

        self::assertNotNull($evaluation);
        self::assertSame(3, $evaluation->totalPoints); // correct_outcome only
        self::assertCount(1, $evaluation->rulePoints);
    }

    public function testStoredEnabledRowActivatesEnabledByDefaultFalseRule(): void
    {
        // The same optional rule, explicitly enabled by a stored row with custom points.
        $evaluator = $this->makeEvaluator([
            'optional_rule' => [true, 7],
        ], [$this->optionalRule()]);

        $guess = RuleTestFactory::guess(3, 0);
        $match = RuleTestFactory::finishedMatch(2, 1);

        $evaluation = $evaluator->evaluate($guess, $match, $this->now);

        self::assertNotNull($evaluation);
        self::assertSame(10, $evaluation->totalPoints); // correct_outcome 3 + optional 7

        $identifiers = [];
        foreach ($evaluation->rulePoints as $rulePoints) {
            $identifiers[] = $rulePoints->ruleIdentifier;
        }
        self::assertContains('optional_rule', $identifiers);
    }

    private function optionalRule(): Rule
    {
        return new class () implements Rule {
            public string $identifier { get => 'optional_rule'; }

            public string $label { get => 'Volitelné pravidlo'; }

            public string $description { get => 'Testovací pravidlo vypnuté ve výchozím stavu.'; }

            public int $defaultPoints { get => 2; }

            public bool $enabledByDefault { get => false; }

            public function evaluate(Guess $guess, SportMatch $match): int
            {
                return 1; // Always hits — participation is driven purely by config.
            }
        };
    }

    /**
     * @param array<string, array{bool, int}> $storedRules identifier → [enabled, points]
     * @param list<Rule>                      $extraRules
     */
    private function makeEvaluator(array $storedRules, array $extraRules = []): GuessEvaluator
    {
        $registry = new RuleRegistry([
            new ExactScoreRule(),
            new CorrectOutcomeRule(),
            new CorrectHomeGoalsRule(),
            new CorrectAwayGoalsRule(),
            ...$extraRules,
        ]);

        $repo = $this->createStub(CompetitionRuleConfigurationRepository::class);

        $competition = RuleTestFactory::competition();
        $configurations = [];
        foreach ($storedRules as $identifier => [$enabled, $points]) {
            $configurations[$identifier] = new CompetitionRuleConfiguration(
                id: Uuid::v7(),
                competition: $competition,
                ruleIdentifier: $identifier,
                enabled: $enabled,
                points: $points,
                now: $this->now,
            );
        }

        $repo->method('mapForCompetition')->willReturn($configurations);

        $identity = $this->createStub(ProvideIdentity::class);
        $identity->method('next')->willReturnCallback(fn () => Uuid::v7());

        return new GuessEvaluator($registry, $repo, $identity);
    }
}
