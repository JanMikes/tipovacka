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
use App\Service\Scoring\CompetitionRuleConfigurationProvisioner;
use App\Service\Scoring\MatchContext;
use App\Tests\Unit\Rule\RuleTestFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionRuleConfigurationProvisionerTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    public function testProvisionCreatesOneConfigPerRule(): void
    {
        $competition = RuleTestFactory::competition();
        $saved = [];

        $repo = $this->createStub(CompetitionRuleConfigurationRepository::class);
        $repo->method('mapForCompetition')->willReturn([]);
        $repo->method('save')->willReturnCallback(function (CompetitionRuleConfiguration $configuration) use (&$saved): void {
            $saved[] = $configuration;
        });

        $provisioner = new CompetitionRuleConfigurationProvisioner(
            $this->makeRegistry(),
            $repo,
            $this->makeIdentity(),
        );

        $provisioner->provision($competition, $this->now);

        self::assertCount(4, $saved);

        $identifiers = array_map(fn (CompetitionRuleConfiguration $c) => $c->ruleIdentifier, $saved);
        self::assertSame(
            ['exact_score', 'correct_outcome', 'correct_home_goals', 'correct_away_goals'],
            $identifiers,
        );

        foreach ($saved as $configuration) {
            self::assertTrue($configuration->enabled);
        }
    }

    public function testProvisionRespectsEnabledByDefaultFalse(): void
    {
        $competition = RuleTestFactory::competition();
        $saved = [];

        $optionalRule = new class () implements Rule {
            public string $identifier { get => 'optional_rule'; }

            public string $label { get => 'Volitelné pravidlo'; }

            public string $description { get => 'Testovací pravidlo vypnuté ve výchozím stavu.'; }

            public int $defaultPoints { get => 2; }

            public bool $enabledByDefault { get => false; }

            public string $category { get => 'scorers'; }

            public function evaluate(Guess $guess, SportMatch $match, MatchContext $context): int
            {
                return 0;
            }
        };

        $repo = $this->createStub(CompetitionRuleConfigurationRepository::class);
        $repo->method('mapForCompetition')->willReturn([]);
        $repo->method('save')->willReturnCallback(function (CompetitionRuleConfiguration $configuration) use (&$saved): void {
            $saved[] = $configuration;
        });

        $provisioner = new CompetitionRuleConfigurationProvisioner(
            new RuleRegistry([new ExactScoreRule(), $optionalRule]),
            $repo,
            $this->makeIdentity(),
        );

        $provisioner->provision($competition, $this->now);

        self::assertCount(2, $saved);

        $byIdentifier = [];
        foreach ($saved as $configuration) {
            $byIdentifier[$configuration->ruleIdentifier] = $configuration;
        }

        self::assertTrue($byIdentifier['exact_score']->enabled);
        self::assertFalse($byIdentifier['optional_rule']->enabled);
        self::assertSame(2, $byIdentifier['optional_rule']->points);
    }

    public function testProvisionIsIdempotent(): void
    {
        $competition = RuleTestFactory::competition();
        $saved = [];

        $existing = [];
        foreach (['exact_score', 'correct_outcome', 'correct_home_goals', 'correct_away_goals'] as $identifier) {
            $existing[$identifier] = new CompetitionRuleConfiguration(
                id: Uuid::v7(),
                competition: $competition,
                ruleIdentifier: $identifier,
                enabled: true,
                points: 1,
                now: $this->now,
            );
        }

        $repo = $this->createStub(CompetitionRuleConfigurationRepository::class);
        $repo->method('mapForCompetition')->willReturn($existing);
        $repo->method('save')->willReturnCallback(function (CompetitionRuleConfiguration $configuration) use (&$saved): void {
            $saved[] = $configuration;
        });

        $provisioner = new CompetitionRuleConfigurationProvisioner(
            $this->makeRegistry(),
            $repo,
            $this->makeIdentity(),
        );

        $provisioner->provision($competition, $this->now);

        self::assertSame([], $saved);
    }

    public function testProvisionOnlyCreatesMissingRules(): void
    {
        $competition = RuleTestFactory::competition();
        $saved = [];

        $repo = $this->createStub(CompetitionRuleConfigurationRepository::class);
        $repo->method('mapForCompetition')->willReturn([
            'exact_score' => new CompetitionRuleConfiguration(
                id: Uuid::v7(),
                competition: $competition,
                ruleIdentifier: 'exact_score',
                enabled: true,
                points: 1,
                now: $this->now,
            ),
        ]);
        $repo->method('save')->willReturnCallback(function (CompetitionRuleConfiguration $configuration) use (&$saved): void {
            $saved[] = $configuration;
        });

        $provisioner = new CompetitionRuleConfigurationProvisioner(
            $this->makeRegistry(),
            $repo,
            $this->makeIdentity(),
        );

        $provisioner->provision($competition, $this->now);

        self::assertCount(3, $saved);
    }

    private function makeRegistry(): RuleRegistry
    {
        return new RuleRegistry([
            new ExactScoreRule(),
            new CorrectOutcomeRule(),
            new CorrectHomeGoalsRule(),
            new CorrectAwayGoalsRule(),
        ]);
    }

    private function makeIdentity(): ProvideIdentity
    {
        $identity = $this->createStub(ProvideIdentity::class);
        $identity->method('next')->willReturnCallback(fn () => Uuid::v7());

        /* @var ProvideIdentity */
        return $identity;
    }
}
