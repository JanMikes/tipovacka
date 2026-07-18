<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateCompetitionRuleConfiguration\UpdateCompetitionRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionRuleConfiguration;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateCompetitionRuleConfigurationHandlerTest extends IntegrationTestCase
{
    public function testUpdatesPointsAndEnabledForExistingRules(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        $this->commandBus()->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: $competitionId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 10],
                'correct_outcome' => ['enabled' => false, 'points' => 3],
            ],
        ));

        $indexed = $this->loadConfigurations($competitionId);

        self::assertArrayHasKey('exact_score', $indexed);
        self::assertTrue($indexed['exact_score']->enabled);
        self::assertSame(10, $indexed['exact_score']->points);

        self::assertArrayHasKey('correct_outcome', $indexed);
        self::assertFalse($indexed['correct_outcome']->enabled);
    }

    public function testIgnoresUnknownRuleIdentifiers(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        $this->commandBus()->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: $competitionId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 7],
                'nonexistent_rule' => ['enabled' => true, 'points' => 99],
            ],
        ));

        self::assertCount(4, $this->loadConfigurations($competitionId));
    }

    public function testClampsNegativePointsToZero(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        $this->commandBus()->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: $competitionId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => -5],
            ],
        ));

        $indexed = $this->loadConfigurations($competitionId);

        self::assertSame(0, $indexed['exact_score']->points);
    }

    public function testPersistsMissingRowOnSave(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $em = $this->entityManager();

        // Remove the stored exact_score row so the handler has to create it.
        $em->createQuery(
            'DELETE FROM '.CompetitionRuleConfiguration::class.' c WHERE c.competition = :competitionId AND c.ruleIdentifier = :identifier',
        )
            ->setParameter('competitionId', $competitionId)
            ->setParameter('identifier', 'exact_score')
            ->execute();

        $this->commandBus()->dispatch(new UpdateCompetitionRuleConfigurationCommand(
            competitionId: $competitionId,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 8],
            ],
        ));

        $indexed = $this->loadConfigurations($competitionId);

        self::assertArrayHasKey('exact_score', $indexed);
        self::assertTrue($indexed['exact_score']->enabled);
        self::assertSame(8, $indexed['exact_score']->points);
    }

    /**
     * @return array<string, CompetitionRuleConfiguration>
     */
    private function loadConfigurations(Uuid $competitionId): array
    {
        $em = $this->entityManager();
        $em->clear();

        /** @var list<CompetitionRuleConfiguration> $configurations */
        $configurations = $em->createQueryBuilder()
            ->select('c')
            ->from(CompetitionRuleConfiguration::class, 'c')
            ->where('c.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($configurations as $configuration) {
            $indexed[$configuration->ruleIdentifier] = $configuration;
        }

        return $indexed;
    }
}
