<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\CompetitionSource;
use App\Entity\MatchSource;
use App\Enum\OvertimeCoverage;
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

        self::assertCount(10, $result->items);

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

        self::assertCount(10, $result->items);

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

    /**
     * Whether the overtime rule could ever score is a fact about the soutěž's
     * zdroje, so the query answers it — no read surface derives it from the
     * entities on its own. PUBLIC_COMPETITION draws from PUBLIC_SOURCE, a
     * knockout zdroj that plays extra time.
     */
    public function testEveryZdrojPlayingExtraTimeIsFullCoverage(): void
    {
        $result = $this->queryBus()->handle(new GetCompetitionRuleConfiguration(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        self::assertSame(OvertimeCoverage::All, $result->overtimeCoverage);
        self::assertNull($result->overtimeCoverage->hint());
    }

    /** VERIFIED_COMPETITION draws only from PRIVATE_SOURCE, which does not. */
    public function testNoZdrojPlayingExtraTimeIsNoCoverage(): void
    {
        $result = $this->queryBus()->handle(new GetCompetitionRuleConfiguration(
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
        ));

        self::assertSame(OvertimeCoverage::None, $result->overtimeCoverage);
        self::assertNotNull($result->overtimeCoverage->hint());
    }

    /**
     * The reason the rule is never hidden or auto-disabled: a soutěž can span
     * zdroje with different flags, and then the rule really does score — just
     * not everywhere.
     */
    public function testAMixedScopeIsPartialCoverage(): void
    {
        $entityManager = $this->entityManager();

        $competition = $entityManager->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertNotNull($competition);

        $withOvertime = $entityManager->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertNotNull($withOvertime);

        $layer = new CompetitionSource(
            id: Uuid::v7(),
            competition: $competition,
            matchSource: $withOvertime,
            addedAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            position: 1,
        );
        $competition->attachSource($layer);
        $entityManager->persist($layer);
        $entityManager->flush();

        $result = $this->queryBus()->handle(new GetCompetitionRuleConfiguration(
            competitionId: $competition->id,
        ));

        self::assertSame(OvertimeCoverage::Partial, $result->overtimeCoverage);
    }
}
