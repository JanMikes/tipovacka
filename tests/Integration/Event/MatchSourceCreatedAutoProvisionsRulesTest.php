<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\CreatePrivateMatchSource\CreatePrivateMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\MatchSourceRuleConfiguration;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class MatchSourceCreatedAutoProvisionsRulesTest extends IntegrationTestCase
{
    public function testCreatingMatchSourceProvisionsFourRuleConfigurations(): void
    {
        $this->commandBus()->dispatch(new CreatePrivateMatchSourceCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Auto-provision test',
            description: null,
            startAt: null,
            endAt: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var MatchSource|null $matchSource */
        $matchSource = $em->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Auto-provision test')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(MatchSource::class, $matchSource);

        /** @var list<MatchSourceRuleConfiguration> $configurations */
        $configurations = $em->createQueryBuilder()
            ->select('c')
            ->from(MatchSourceRuleConfiguration::class, 'c')
            ->where('c.matchSource = :matchSourceId')
            ->setParameter('matchSourceId', $matchSource->id)
            ->getQuery()
            ->getResult();

        self::assertCount(4, $configurations);

        $identifiers = array_map(fn (MatchSourceRuleConfiguration $c) => $c->ruleIdentifier, $configurations);
        sort($identifiers);
        self::assertSame(
            ['correct_away_goals', 'correct_home_goals', 'correct_outcome', 'exact_score'],
            $identifiers,
        );
    }
}
