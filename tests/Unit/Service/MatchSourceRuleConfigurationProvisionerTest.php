<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\MatchSourceRuleConfiguration;
use App\Repository\MatchSourceRuleConfigurationRepository;
use App\Rule\CorrectAwayGoalsRule;
use App\Rule\CorrectHomeGoalsRule;
use App\Rule\CorrectOutcomeRule;
use App\Rule\ExactScoreRule;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;
use App\Service\Scoring\MatchSourceRuleConfigurationProvisioner;
use App\Tests\Unit\Rule\RuleTestFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MatchSourceRuleConfigurationProvisionerTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    public function testProvisionCreatesOneConfigPerRule(): void
    {
        $matchSource = RuleTestFactory::matchSource();
        $saved = [];

        $repo = $this->createStub(MatchSourceRuleConfigurationRepository::class);
        $repo->method('findOne')->willReturn(null);
        $repo->method('save')->willReturnCallback(function (MatchSourceRuleConfiguration $configuration) use (&$saved): void {
            $saved[] = $configuration;
        });

        $provisioner = new MatchSourceRuleConfigurationProvisioner(
            $this->makeRegistry(),
            $repo,
            $this->makeIdentity(),
        );

        $provisioner->provision($matchSource, $this->now);

        self::assertCount(4, $saved);

        $identifiers = array_map(fn (MatchSourceRuleConfiguration $c) => $c->ruleIdentifier, $saved);
        self::assertSame(
            ['exact_score', 'correct_outcome', 'correct_home_goals', 'correct_away_goals'],
            $identifiers,
        );
    }

    public function testProvisionIsIdempotent(): void
    {
        $matchSource = RuleTestFactory::matchSource();
        $saved = [];

        $repo = $this->createStub(MatchSourceRuleConfigurationRepository::class);
        // Simulate all rules already exist.
        $repo->method('findOne')->willReturnCallback(fn (Uuid $matchSourceId, string $identifier) => new MatchSourceRuleConfiguration(
            id: Uuid::v7(),
            matchSource: $matchSource,
            ruleIdentifier: $identifier,
            enabled: true,
            points: 1,
            now: $this->now,
        ));
        $repo->method('save')->willReturnCallback(function (MatchSourceRuleConfiguration $configuration) use (&$saved): void {
            $saved[] = $configuration;
        });

        $provisioner = new MatchSourceRuleConfigurationProvisioner(
            $this->makeRegistry(),
            $repo,
            $this->makeIdentity(),
        );

        $provisioner->provision($matchSource, $this->now);

        self::assertSame([], $saved);
    }

    public function testProvisionOnlyCreatesMissingRules(): void
    {
        $matchSource = RuleTestFactory::matchSource();
        $saved = [];

        $repo = $this->createStub(MatchSourceRuleConfigurationRepository::class);
        $repo->method('findOne')->willReturnCallback(fn (Uuid $matchSourceId, string $identifier): ?MatchSourceRuleConfiguration => 'exact_score' === $identifier
            ? new MatchSourceRuleConfiguration(
                id: Uuid::v7(),
                matchSource: $matchSource,
                ruleIdentifier: $identifier,
                enabled: true,
                points: 1,
                now: $this->now,
            )
            : null);
        $repo->method('save')->willReturnCallback(function (MatchSourceRuleConfiguration $configuration) use (&$saved): void {
            $saved[] = $configuration;
        });

        $provisioner = new MatchSourceRuleConfigurationProvisioner(
            $this->makeRegistry(),
            $repo,
            $this->makeIdentity(),
        );

        $provisioner->provision($matchSource, $this->now);

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
