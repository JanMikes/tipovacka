<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionRuleConfiguration;
use App\Query\GetCompetitionRuleConfiguration\GetCompetitionRuleConfiguration;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetCompetitionRuleConfigurationQueryTest extends IntegrationTestCase
{
    public function testReturnsAllRegisteredRulesWithCompetitionConfig(): void
    {
        $result = $this->queryBus()->handle(new GetCompetitionRuleConfiguration(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        self::assertCount(8, $result->items);

        $identifiers = array_map(fn ($item) => $item->identifier, $result->items);
        self::assertContains('exact_score', $identifiers);
        self::assertContains('correct_outcome', $identifiers);
        self::assertContains('correct_home_goals', $identifiers);
        self::assertContains('correct_away_goals', $identifiers);
        self::assertContains('scorer_hit', $identifiers);
        self::assertContains('period_exact', $identifiers);
        self::assertContains('period_tendency', $identifiers);
        self::assertContains('overtime_exact', $identifiers);

        $baseIdentifiers = ['exact_score', 'correct_outcome', 'correct_home_goals', 'correct_away_goals'];

        foreach ($result->items as $item) {
            // Base rules enabled; S06 optional rules disabled (PUBLIC has no rows for them).
            self::assertSame(in_array($item->identifier, $baseIdentifiers, true), $item->enabled);
            self::assertSame($item->defaultPoints, $item->points);
        }
    }

    public function testMissingStoredRowFallsBackToRuleDefaults(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        // Drop the stored exact_score row — the query must fall back to the rule's
        // enabledByDefault + defaultPoints (the same semantics the evaluator uses).
        $this->entityManager()->createQuery(
            'DELETE FROM '.CompetitionRuleConfiguration::class.' c WHERE c.competition = :competitionId AND c.ruleIdentifier = :identifier',
        )
            ->setParameter('competitionId', $competitionId)
            ->setParameter('identifier', 'exact_score')
            ->execute();

        $result = $this->queryBus()->handle(new GetCompetitionRuleConfiguration(
            competitionId: $competitionId,
        ));

        self::assertCount(8, $result->items);

        $exactScore = null;
        foreach ($result->items as $item) {
            if ('exact_score' === $item->identifier) {
                $exactScore = $item;
            }
        }

        self::assertNotNull($exactScore);
        self::assertTrue($exactScore->enabled); // enabledByDefault of ExactScoreRule
        self::assertSame(5, $exactScore->points); // defaultPoints
        self::assertSame(5, $exactScore->defaultPoints);
    }

    public function testStoredRowWinsOverDefaults(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        /** @var CompetitionRuleConfiguration $stored */
        $stored = $this->entityManager()->find(
            CompetitionRuleConfiguration::class,
            Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_RULE_EXACT_SCORE_ID),
        );
        $stored->enable(42, new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $this->entityManager()->flush();

        $result = $this->queryBus()->handle(new GetCompetitionRuleConfiguration(
            competitionId: $competitionId,
        ));

        foreach ($result->items as $item) {
            if ('exact_score' === $item->identifier) {
                self::assertSame(42, $item->points);
                self::assertSame(5, $item->defaultPoints);
            }
        }
    }

    public function testReportsEvaluationCountPerCompetition(): void
    {
        $withEvaluation = $this->queryBus()->handle(new GetCompetitionRuleConfiguration(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        self::assertSame(1, $withEvaluation->evaluationCount);

        $withoutEvaluation = $this->queryBus()->handle(new GetCompetitionRuleConfiguration(
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
        ));

        self::assertSame(0, $withoutEvaluation->evaluationCount);
    }
}
