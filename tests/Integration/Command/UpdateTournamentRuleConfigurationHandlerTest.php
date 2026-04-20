<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateTournamentRuleConfiguration\UpdateTournamentRuleConfigurationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\TournamentRuleConfiguration;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateTournamentRuleConfigurationHandlerTest extends IntegrationTestCase
{
    public function testUpdatesPointsAndEnabledForExistingRules(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);

        $this->commandBus()->dispatch(new UpdateTournamentRuleConfigurationCommand(
            tournamentId: $tournamentId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 10],
                'correct_outcome' => ['enabled' => false, 'points' => 3],
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<TournamentRuleConfiguration> $configurations */
        $configurations = $em->createQueryBuilder()
            ->select('c')
            ->from(TournamentRuleConfiguration::class, 'c')
            ->where('c.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournamentId)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($configurations as $configuration) {
            $indexed[$configuration->ruleIdentifier] = $configuration;
        }

        self::assertArrayHasKey('exact_score', $indexed);
        self::assertTrue($indexed['exact_score']->enabled);
        self::assertSame(10, $indexed['exact_score']->points);

        self::assertArrayHasKey('correct_outcome', $indexed);
        self::assertFalse($indexed['correct_outcome']->enabled);
    }

    public function testIgnoresUnknownRuleIdentifiers(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);

        $this->commandBus()->dispatch(new UpdateTournamentRuleConfigurationCommand(
            tournamentId: $tournamentId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            changes: [
                'exact_score' => ['enabled' => true, 'points' => 7],
                'nonexistent_rule' => ['enabled' => true, 'points' => 99],
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<TournamentRuleConfiguration> $configurations */
        $configurations = $em->createQueryBuilder()
            ->select('c')
            ->from(TournamentRuleConfiguration::class, 'c')
            ->where('c.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournamentId)
            ->getQuery()
            ->getResult();

        self::assertCount(4, $configurations);
    }
}
