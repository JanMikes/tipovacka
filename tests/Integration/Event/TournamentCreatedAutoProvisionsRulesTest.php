<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\CreatePrivateTournament\CreatePrivateTournamentCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Entity\TournamentRuleConfiguration;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class TournamentCreatedAutoProvisionsRulesTest extends IntegrationTestCase
{
    public function testCreatingTournamentProvisionsFourRuleConfigurations(): void
    {
        $this->commandBus()->dispatch(new CreatePrivateTournamentCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Auto-provision test',
            description: null,
            startAt: null,
            endAt: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var Tournament|null $tournament */
        $tournament = $em->createQueryBuilder()
            ->select('t')
            ->from(Tournament::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Auto-provision test')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Tournament::class, $tournament);

        /** @var list<TournamentRuleConfiguration> $configurations */
        $configurations = $em->createQueryBuilder()
            ->select('c')
            ->from(TournamentRuleConfiguration::class, 'c')
            ->where('c.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournament->id)
            ->getQuery()
            ->getResult();

        self::assertCount(4, $configurations);

        $identifiers = array_map(fn (TournamentRuleConfiguration $c) => $c->ruleIdentifier, $configurations);
        sort($identifiers);
        self::assertSame(
            ['correct_away_goals', 'correct_home_goals', 'correct_outcome', 'exact_score'],
            $identifiers,
        );
    }
}
