<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionTeamFilter;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Enum\CompetitionMatchSelectionMode;
use App\Service\Competition\CompetitionMatchProvider;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The three implementations of „does match M belong to competition C" must never
 * drift apart: `includes()` (entity-wise, what the match detail page asks),
 * `applyCompetitionMatchFilter` (competition-scoped SQL) and
 * `applyRowLevelCompetitionMatchFilter` (the cross-competition row-wise SQL).
 *
 * B4 was reported as „the match detail omits a competition I am a member of",
 * and the documented way that class of bug happens is a selection mode taught to
 * one filter but not the other. This test pins all three together for every mode.
 */
final class CompetitionMatchScopeAgreementTest extends IntegrationTestCase
{
    public function testAllThreeMembershipImplementationsAgreeForEveryMode(): void
    {
        $this->createSubsetCompetition();
        $this->createTeamsCompetition();
        $this->createPlayoffExcludingCompetition();

        $sourceMatches = $this->publicSourceMatches();
        self::assertNotSame([], $sourceMatches);

        $checked = 0;

        foreach ($this->competitionsOnPublicSource() as $competition) {
            foreach ($sourceMatches as $match) {
                $expected = $this->provider()->includes($competition, $match);

                self::assertSame(
                    $expected,
                    in_array($match->id->toRfc4122(), $this->scopedFilterMatchIds($competition), true),
                    sprintf('applyCompetitionMatchFilter disagrees for %s / %s', $competition->name, $match->id->toRfc4122()),
                );
                self::assertSame(
                    $expected,
                    in_array($match->id->toRfc4122(), $this->rowLevelFilterMatchIds($competition), true),
                    sprintf('applyRowLevelCompetitionMatchFilter disagrees for %s / %s', $competition->name, $match->id->toRfc4122()),
                );

                ++$checked;
            }
        }

        self::assertGreaterThan(0, $checked);
    }

    /**
     * The exact way the row-wise filter used to drift: it OR-ed the three mode
     * branches instead of guarding each by the row's own `selectionMode`, so a
     * leftover row of an unused mode widened a competition's scope on
     * cross-competition surfaces only — the match detail page (which asks
     * `includes()`) kept saying the match is out of scope.
     */
    public function testStaleTeamFilterRowsDoNotWidenASubsetCompetition(): void
    {
        $competition = $this->createSubsetCompetition();

        // Plzeň plays MATCH_LIVE, which is NOT in the subset selection.
        $this->addTeamFilterRow($competition, AppFixtures::TEAM_PLZEN_ID);

        $liveMatch = $this->match(AppFixtures::MATCH_LIVE_ID);

        self::assertFalse($this->provider()->includes($competition, $liveMatch));
        self::assertNotContains(AppFixtures::MATCH_LIVE_ID, $this->scopedFilterMatchIds($competition));
        self::assertNotContains(AppFixtures::MATCH_LIVE_ID, $this->rowLevelFilterMatchIds($competition));
    }

    private function createSubsetCompetition(): Competition
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Scope — subset',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            selectionMode: CompetitionMatchSelectionMode::Subset,
            selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_FINISHED_ID)],
        ));

        return $this->competitionByName('Scope — subset');
    }

    private function createTeamsCompetition(): Competition
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Scope — teams',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            selectionMode: CompetitionMatchSelectionMode::Teams,
            filterTeamIds: [Uuid::fromString(AppFixtures::TEAM_SPARTA_ID)],
        ));

        return $this->competitionByName('Scope — teams');
    }

    private function createPlayoffExcludingCompetition(): Competition
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Scope — no playoff',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            includePlayoff: false,
        ));

        return $this->competitionByName('Scope — no playoff');
    }

    private function addTeamFilterRow(Competition $competition, string $teamId): void
    {
        $team = $this->entityManager()->find(Team::class, Uuid::fromString($teamId));
        self::assertInstanceOf(Team::class, $team);

        $this->entityManager()->persist(new CompetitionTeamFilter(
            id: Uuid::fromString('019eeee1-0000-7000-8000-0000000000b1'),
            competition: $competition,
            team: $team,
            addedAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        ));
        $this->entityManager()->flush();
        $this->provider()->forgetTeamFilters($competition->id);
    }

    /**
     * @return list<string>
     */
    private function scopedFilterMatchIds(Competition $competition): array
    {
        return array_map(
            static fn (SportMatch $match): string => $match->id->toRfc4122(),
            $this->provider()->matchesFor($competition),
        );
    }

    /**
     * @return list<string>
     */
    private function rowLevelFilterMatchIds(Competition $competition): array
    {
        $qb = $this->entityManager()->createQueryBuilder()
            ->select('m.id AS matchId')
            ->from(Competition::class, 'c')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.matchSource = c.matchSource')
            ->where('c.id = :competitionId')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('competitionId', $competition->id);

        $this->provider()->applyRowLevelCompetitionMatchFilter($qb, 'm', 'c');

        /** @var list<array{matchId: Uuid}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return array_map(static fn (array $row): string => $row['matchId']->toRfc4122(), $rows);
    }

    /**
     * @return list<SportMatch>
     */
    private function publicSourceMatches(): array
    {
        /** @var list<SportMatch> $matches */
        $matches = $this->entityManager()->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.matchSource = :sourceId')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('sourceId', Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID))
            ->getQuery()
            ->getResult();

        return $matches;
    }

    /**
     * @return list<Competition>
     */
    private function competitionsOnPublicSource(): array
    {
        /** @var list<Competition> $competitions */
        $competitions = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(Competition::class, 'c')
            ->where('c.matchSource = :sourceId')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('sourceId', Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID))
            ->getQuery()
            ->getResult();

        return $competitions;
    }

    private function competitionByName(string $name): Competition
    {
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
