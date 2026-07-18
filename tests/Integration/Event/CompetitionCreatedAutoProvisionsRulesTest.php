<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionRuleConfiguration;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionCreatedAutoProvisionsRulesTest extends IntegrationTestCase
{
    public function testCreatingCompetitionProvisionsFourRuleConfigurations(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            name: 'Auto-provision test',
            description: null,
            withPin: false,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var Competition|null $competition */
        $competition = $em->createQueryBuilder()
            ->select('c')
            ->from(Competition::class, 'c')
            ->where('c.name = :name')
            ->setParameter('name', 'Auto-provision test')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);

        /** @var list<CompetitionRuleConfiguration> $configurations */
        $configurations = $em->createQueryBuilder()
            ->select('c')
            ->from(CompetitionRuleConfiguration::class, 'c')
            ->where('c.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(4, $configurations);

        $identifiers = array_map(fn (CompetitionRuleConfiguration $c) => $c->ruleIdentifier, $configurations);
        sort($identifiers);
        self::assertSame(
            ['correct_away_goals', 'correct_home_goals', 'correct_outcome', 'exact_score'],
            $identifiers,
        );

        foreach ($configurations as $configuration) {
            self::assertTrue($configuration->enabled);
        }
    }
}
