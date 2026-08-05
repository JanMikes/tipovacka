<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionSource;
use App\Entity\CompetitionTeamFilter;
use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Enum\CompetitionMatchSelectionMode;
use App\Repository\CompetitionRepository;
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

    /**
     * The multi-source shape the layers exist for: ONE soutěž taking Sparta's
     * matches from the public zdroj and everything from a second, private one.
     * All three membership implementations must agree on every match of both
     * zdroje — and on matches of neither.
     */
    public function testAllThreeImplementationsAgreeForAMultiSourceCompetition(): void
    {
        $competition = $this->createMultiSourceCompetition();

        $checked = 0;

        foreach ($this->publicSourceMatches() as $match) {
            $expected = $this->provider()->includes($competition, $match);

            self::assertSame(
                $expected,
                in_array($match->id->toRfc4122(), $this->scopedFilterMatchIds($competition), true),
                sprintf('applyCompetitionMatchFilter disagrees for %s', $match->id->toRfc4122()),
            );
            self::assertSame(
                $expected,
                in_array($match->id->toRfc4122(), $this->rowLevelFilterMatchIds($competition), true),
                sprintf('applyRowLevelCompetitionMatchFilter disagrees for %s', $match->id->toRfc4122()),
            );

            ++$checked;
        }

        self::assertGreaterThan(0, $checked);
    }

    /**
     * Each layer answers for its OWN zdroj: the public one is team-filtered to
     * Sparta, the private one takes everything. A Sparta-less public match
     * stays out even though the private layer is mode All, and the private
     * match comes in even though it matches no team filter.
     */
    public function testEachLayerRulesOnlyItsOwnSource(): void
    {
        $competition = $this->createMultiSourceCompetition();
        $included = $this->scopedFilterMatchIds($competition);

        // Private layer (mode All) contributes its match…
        self::assertContains(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID, $included);
        self::assertTrue($this->provider()->includes($competition, $this->match(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID)));

        // …while the public layer still only lets Sparta through. MATCH_LIVE is
        // Plzeň–Baník: in the source the competition draws from, but filtered out.
        self::assertNotContains(AppFixtures::MATCH_LIVE_ID, $included);
        self::assertFalse($this->provider()->includes($competition, $this->match(AppFixtures::MATCH_LIVE_ID)));

        // Sparta's public match is in.
        self::assertContains(AppFixtures::MATCH_SCHEDULED_ID, $included);
    }

    /**
     * A competition that draws from a zdroj must be reachable FROM that zdroj —
     * through any layer, not just its headline one. This is the fan-in every
     * source-driven side effect starts from (match added, source completed …).
     */
    public function testCompetitionIsFoundFromEveryZdrojItDrawsFrom(): void
    {
        $competition = $this->createMultiSourceCompetition();

        $repository = self::getContainer()->get(CompetitionRepository::class);

        foreach ([AppFixtures::PUBLIC_SOURCE_ID, AppFixtures::PRIVATE_SOURCE_ID] as $sourceId) {
            $found = array_map(
                static fn (Competition $c): string => $c->id->toRfc4122(),
                $repository->findByMatchSource(Uuid::fromString($sourceId)),
            );

            self::assertContains($competition->id->toRfc4122(), $found, sprintf('not reachable from zdroj %s', $sourceId));
        }
    }

    /**
     * „Schedule known-complete" is an AND over the layers: one still-running
     * zdroj keeps the whole soutěž open, so the competition-ended notification
     * cannot fire on a half-finished multi-source competition.
     */
    public function testScheduleIsCompleteOnlyWhenEveryLayersZdrojIsCompleted(): void
    {
        $competition = $this->createMultiSourceCompetition();

        self::assertFalse($competition->scheduleIsComplete);

        $em = $this->entityManager();
        $public = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $public);
        $public->markCompleted(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $em->flush();

        // One zdroj down, one to go.
        self::assertFalse($competition->scheduleIsComplete);

        $private = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $private);
        $private->markCompleted(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $em->flush();

        self::assertTrue($competition->scheduleIsComplete);
    }

    /**
     * A soutěž whose public layer filters on Sparta and whose private layer is
     * mode All. The second layer is attached directly — the wizard learns to
     * compose these in C3.
     */
    private function createMultiSourceCompetition(): Competition
    {
        $competition = $this->createTeamsCompetition();

        $em = $this->entityManager();
        $privateSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $privateSource);

        $layer = new CompetitionSource(
            id: Uuid::fromString('019eeee1-0000-7000-8000-0000000000c9'),
            competition: $competition,
            matchSource: $privateSource,
            addedAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            position: 1,
        );
        $competition->attachSource($layer);
        $em->persist($layer);
        $em->flush();

        return $competition;
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
            competitionSource: $competition->sources[0],
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
        // Deliberately joined WITHOUT any source agreement (`1 = 1`): the
        // row-level predicate must carry that itself. Before multi-source it
        // did not, and callers had to remember `m.matchSource = c.headlineSource`
        // — an unenforced obligation that would now silently pin a soutěž to
        // its headline zdroj.
        $qb = $this->entityManager()->createQueryBuilder()
            ->select('m.id AS matchId')
            ->from(Competition::class, 'c')
            ->innerJoin(SportMatch::class, 'm', 'WITH', '1 = 1')
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
        // Every live match in the database, not just the public source's: a
        // competition must disagree about foreign matches just as consistently
        // as it agrees about its own.
        /** @var list<SportMatch> $matches */
        $matches = $this->entityManager()->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.deletedAt IS NULL')
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
            ->where('c.headlineSource = :sourceId')
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
