<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Enum\CompetitionMatchSelectionMode;
use App\Service\Competition\CompetitionMatchProvider;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Real-SQL coverage of CompetitionMatchProvider's Teams mode: a team-filtered
 * competition includes exactly the source matches where a filter team plays
 * (home OR away), and a team's later-added match auto-joins (the dynamic
 * behaviour the playoff scenario depends on).
 */
final class CompetitionTeamFilterProviderTest extends IntegrationTestCase
{
    public function testMatchesForReturnsOnlyFilterTeamsMatchesAndAutoJoinsNewOnes(): void
    {
        // Filter the curated Champions-League source by Sparta (plays MATCH_SCHEDULED,
        // home) and Real Madrid (plays MATCH_PLAYOFF, home).
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Sparta & Real',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            selectionMode: CompetitionMatchSelectionMode::Teams,
            filterTeamIds: [
                Uuid::fromString(AppFixtures::TEAM_SPARTA_ID),
                Uuid::fromString(AppFixtures::TEAM_REAL_MADRID_ID),
            ],
        ));

        $competition = $this->competitionByName('Sparta & Real');

        $matchIds = $this->matchIdsFor($competition);

        // Sparta vs Slavia + Real Madrid vs Barcelona (playoff) — nothing else.
        self::assertContains(AppFixtures::MATCH_SCHEDULED_ID, $matchIds);
        self::assertContains(AppFixtures::MATCH_PLAYOFF_ID, $matchIds);
        self::assertNotContains(AppFixtures::MATCH_LIVE_ID, $matchIds);     // Plzeň vs Baník
        self::assertNotContains(AppFixtures::MATCH_FINISHED_ID, $matchIds); // Bohemians vs Jablonec

        $provider = $this->provider();
        self::assertTrue($provider->includes($competition, $this->match(AppFixtures::MATCH_SCHEDULED_ID)));
        self::assertFalse($provider->includes($competition, $this->match(AppFixtures::MATCH_LIVE_ID)));

        // A Sparta fixture imported AFTER creation joins with no edit (dynamic filter).
        $newMatchId = '019eeee0-0000-7000-8000-0000000000a1';
        $this->addMatch($newMatchId, AppFixtures::TEAM_SPARTA_ID, AppFixtures::TEAM_BANIK_ID);

        self::assertContains($newMatchId, $this->matchIdsFor($competition));
    }

    /**
     * @return list<string>
     */
    private function matchIdsFor(Competition $competition): array
    {
        return array_map(
            static fn (SportMatch $match): string => $match->id->toRfc4122(),
            $this->provider()->matchesFor($competition),
        );
    }

    private function addMatch(string $id, string $homeTeamId, string $awayTeamId): void
    {
        $em = $this->entityManager();

        $match = new SportMatch(
            id: Uuid::fromString($id),
            matchSource: $this->match(AppFixtures::MATCH_SCHEDULED_ID)->matchSource,
            homeTeam: $this->team($homeTeamId),
            awayTeam: $this->team($awayTeamId),
            kickoffAt: new \DateTimeImmutable('2025-07-01 18:00:00 UTC'),
            venue: null,
            createdAt: new \DateTimeImmutable('2025-06-16 12:00:00 UTC'),
        );
        $match->popEvents();

        $em->persist($match);
        $em->flush();
    }

    private function team(string $id): Team
    {
        $team = $this->entityManager()->find(Team::class, Uuid::fromString($id));
        self::assertInstanceOf(Team::class, $team);

        return $team;
    }

    private function competitionByName(string $name): Competition
    {
        $this->entityManager()->clear();

        $competition = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(Competition::class, 'c')
            ->where('c.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function match(string $id): SportMatch
    {
        $match = $this->entityManager()->find(SportMatch::class, Uuid::fromString($id));
        self::assertInstanceOf(SportMatch::class, $match);

        return $match;
    }

    private function provider(): CompetitionMatchProvider
    {
        /* @var CompetitionMatchProvider */
        return self::getContainer()->get(CompetitionMatchProvider::class);
    }
}
