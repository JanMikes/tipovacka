<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Enum\CompetitionMatchSelectionMode;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\ScopeDraftResolver;
use App\Tests\Support\IntegrationTestCase;
use App\Value\CompetitionSourceSpec;
use Symfony\Component\Uid\Uuid;

/**
 * The draft resolver previews a scope that does not exist yet, so it cannot ask
 * {@see CompetitionMatchProvider} — it re-implements the per-layer rules. The
 * two must never disagree, or the wizard would promise a soutěž different from
 * the one it creates. These tests pin them together.
 */
final class ScopeDraftResolverTest extends IntegrationTestCase
{
    public function testDraftMatchesWhatTheCreatedCompetitionActuallyIncludes(): void
    {
        $specs = [
            new CompetitionSourceSpec(
                matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
                selectionMode: CompetitionMatchSelectionMode::Teams,
                filterTeamIds: [Uuid::fromString(AppFixtures::TEAM_SPARTA_ID)],
            ),
            new CompetitionSourceSpec(
                matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            ),
        ];

        $draft = $this->resolver()->resolve($specs);

        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Draft agreement',
            matchSourceId: $specs[0]->matchSourceId,
            sportId: null,
            fromScratch: false,
            withPin: false,
            selectionMode: $specs[0]->selectionMode,
            filterTeamIds: $specs[0]->filterTeamIds,
            additionalSources: [$specs[1]],
        ));

        $competition = $this->competitionByName('Draft agreement');

        $previewed = array_map(static fn (SportMatch $m): string => $m->id->toRfc4122(), $draft->matches);
        $actual = array_map(
            static fn (SportMatch $m): string => $m->id->toRfc4122(),
            self::getContainer()->get(CompetitionMatchProvider::class)->matchesFor($competition),
        );

        self::assertNotSame([], $previewed, 'An empty preview would make the assertion vacuous.');
        self::assertSame($previewed, $actual);
    }

    public function testDraftSpansEveryLayersZdroj(): void
    {
        $draft = $this->resolver()->resolve([
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)),
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID)),
        ]);

        $sourceIds = [];

        foreach ($draft->matches as $match) {
            $sourceIds[$match->matchSource->id->toRfc4122()] = true;
        }

        self::assertArrayHasKey(AppFixtures::PUBLIC_SOURCE_ID, $sourceIds);
        self::assertArrayHasKey(AppFixtures::PRIVATE_SOURCE_ID, $sourceIds);
        self::assertNotNull($draft->firstKickoff);
        self::assertNotNull($draft->lastKickoff);
        self::assertLessThanOrEqual($draft->lastKickoff, $draft->firstKickoff);
    }

    /**
     * The whole point of the warning: the same fixture typed by hand into a
     * private zdroj while a curated one already carries it. The teams are
     * DIFFERENT `Team` rows (global directory vs local), so only the names can
     * catch it.
     */
    public function testSameFixtureFromTwoZdrojeIsReportedAsADuplicate(): void
    {
        $this->copyScheduledMatchIntoPrivateSource(kickoffShift: '+2 hours');

        $draft = $this->resolver()->resolve([
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)),
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID)),
        ]);

        self::assertTrue($draft->hasDuplicates);
        self::assertCount(1, $draft->duplicates);
        self::assertCount(2, $draft->duplicates[0]->matches);
        self::assertCount(2, $draft->duplicates[0]->sourceNames);
    }

    /** Far enough apart to be a second leg, not a double entry. */
    public function testSameTeamsWellOutsideTheWindowAreNotADuplicate(): void
    {
        $this->copyScheduledMatchIntoPrivateSource(kickoffShift: '+8 days');

        $draft = $this->resolver()->resolve([
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)),
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID)),
        ]);

        self::assertFalse($draft->hasDuplicates);
    }

    /** A single zdroj taken twice contributes its matches once, not twice. */
    public function testTheSameZdrojTwiceDoesNotDoubleItsMatches(): void
    {
        $once = $this->resolver()->resolve([
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)),
        ]);
        $twice = $this->resolver()->resolve([
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)),
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)),
        ]);

        self::assertSame($once->matchCount, $twice->matchCount);
    }

    /**
     * Creates, in the PRIVATE zdroj, a fixture with the same team names as
     * MATCH_SCHEDULED — but its own local `Team` rows, exactly as
     * TeamResolver would when someone types those names into a private zdroj.
     */
    private function copyScheduledMatchIntoPrivateSource(string $kickoffShift): void
    {
        $em = $this->entityManager();

        $original = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $original);

        $privateSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $privateSource);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $localHome = new Team(id: Uuid::v7(), sport: $privateSource->sport, matchSource: $privateSource, name: $original->homeTeam->name, createdAt: $now);
        $localAway = new Team(id: Uuid::v7(), sport: $privateSource->sport, matchSource: $privateSource, name: $original->awayTeam->name, createdAt: $now);
        $em->persist($localHome);
        $em->persist($localAway);

        $copy = new SportMatch(
            id: Uuid::v7(),
            matchSource: $privateSource,
            homeTeam: $localHome,
            awayTeam: $localAway,
            kickoffAt: $original->kickoffAt->modify($kickoffShift),
            venue: null,
            createdAt: $now,
        );
        $copy->popEvents();
        $em->persist($copy);
        $em->flush();
    }

    /**
     * Built directly rather than pulled from the container: the resolver has a
     * single dependency, and as a service used only by the wizard it gets
     * inlined, so it is not fetchable by id.
     */
    private function resolver(): ScopeDraftResolver
    {
        return new ScopeDraftResolver($this->entityManager());
    }

    private function competitionByName(string $name): Competition
    {
        $competition = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(Competition::class, 'c')
            ->where('c.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleResult();

        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }
}
