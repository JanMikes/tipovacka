<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Feed;

use App\Command\AddTeamAlias\AddTeamAliasCommand;
use App\Command\SyncMatchSourceFeed\SyncMatchSourceFeedCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Enum\FeedMatchStatus;
use App\Enum\FeedProvider;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Enum\SportMatchState;
use App\Repository\MatchEventRepository;
use App\Repository\SportMatchRepository;
use App\Repository\TeamRepository;
use App\Service\Feed\FeedSynchronizer;
use App\Service\Feed\FeedSyncResult;
use App\Service\Feed\MatchSnapshot;
use App\Tests\Support\IntegrationTestCase;
use App\Value\MatchEventInput;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * End-to-end pipeline test over the fixture-backed curated PUBLIC source: the
 * snapshots are built in code (exactly what a MatchDataProvider would return)
 * and applied through SyncMatchSourceFeedCommand — the same transaction and
 * domain-event path production uses. No network anywhere.
 */
final class FeedSynchronizerTest extends IntegrationTestCase
{
    private const string EXTERNAL_ID = 'feed-1001';

    public function testCreatesMatchFromSnapshotWithExistingTeams(): void
    {
        $result = $this->syncPublicSource([$this->scheduledSnapshot()]);

        self::assertCount(1, $result->created);
        self::assertSame(0, $result->unchanged);

        $match = $this->findSynced();
        self::assertSame(self::EXTERNAL_ID, $match->externalId);
        self::assertSame(SportMatchState::Scheduled, $match->state);
        self::assertEquals(new \DateTimeImmutable('2025-07-01 18:00:00 UTC'), $match->kickoffAt);
        self::assertSame('epet ARENA', $match->venue);
        self::assertSame('1. kolo', $match->round);
        self::assertSame(AppFixtures::TEAM_SPARTA_ID, (string) $match->homeTeam->id);
        self::assertSame(AppFixtures::TEAM_SLAVIA_ID, (string) $match->awayTeam->id);
    }

    /**
     * The whole-season problem: a feed lists rounds played before this source
     * existed (FAČR serves all 240 Chance Liga zápasů against the 224 seeded
     * from kolo 3). Creating them would drop untippable matches into a running
     * soutěž where every member scores zero on them, forever.
     */
    public function testDoesNotCreateAMatchThatHasAlreadyKickedOff(): void
    {
        // MockClock stands at 2025-06-15 12:00 UTC.
        $result = $this->syncPublicSource([$this->snapshot(
            status: FeedMatchStatus::Finished,
            kickoffUtc: '2025-06-14 18:00:00 UTC',
            homeScore: 2,
            awayScore: 1,
        )]);

        self::assertSame([], $result->created);
        self::assertCount(1, $result->skippedPastKickoff);
        self::assertNull($this->sportMatches()->findBySourceAndExternalId(
            Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            self::EXTERNAL_ID,
        ));
    }

    /**
     * Long-past rounds are not news — reporting them on every poll would bury
     * the one recent miss that IS worth a look.
     */
    public function testAnAncientFixtureIsSkippedWithoutBeingReported(): void
    {
        $result = $this->syncPublicSource([$this->snapshot(
            status: FeedMatchStatus::Finished,
            kickoffUtc: '2025-01-10 18:00:00 UTC',
            homeScore: 1,
            awayScore: 0,
        )]);

        self::assertSame([], $result->created);
        self::assertSame([], $result->skippedPastKickoff);
    }

    /**
     * The guard is about CREATION only — a match we already track still accepts
     * its result after kickoff, which is the entire point of the feed.
     */
    public function testAnExistingMatchStillReceivesItsResultAfterKickoff(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);

        $result = $this->syncPublicSource([$this->snapshot(
            status: FeedMatchStatus::Finished,
            kickoffUtc: '2025-07-01 18:00:00 UTC',
            homeScore: 3,
            awayScore: 0,
        )]);

        self::assertCount(1, $result->finished);
        self::assertSame(3, $this->findSynced()->homeScore);
    }

    /**
     * The 2026-08-08 incident, as a test. A source bound to a feed whose ids it
     * has not adopted must import NOTHING: every fixture looks unseen, so the
     * sync would build a duplicate season next to the one people are tipping.
     */
    public function testRefusesToCreateOnASourceThatHasNotAdoptedTheFeedIds(): void
    {
        // One stored match carrying an id from a DIFFERENT namespace.
        $this->syncPublicSource([$this->scheduledSnapshot()]);
        $before = count($this->sportMatches()->listByMatchSource(Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)));

        $result = $this->syncPublicSource([
            $this->snapshot(status: FeedMatchStatus::Scheduled, externalId: 'other-namespace-1'),
            $this->snapshot(status: FeedMatchStatus::Scheduled, externalId: 'other-namespace-2'),
        ]);

        self::assertSame([], $result->created);
        self::assertNotSame([], $result->needsAdoption);
        self::assertStringContainsString('adopt-external-ids', $result->needsAdoption[0]);
        self::assertTrue($result->hasFailures, 'an unbridged source must fail the cron, not pass quietly');
        self::assertCount($before, $this->sportMatches()->listByMatchSource(Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)));
    }

    /** An empty source is a first import, not an unadopted one. */
    public function testAnEmptySourceStillImportsEverything(): void
    {
        $result = $this->syncPublicSource([
            $this->snapshot(status: FeedMatchStatus::Scheduled, externalId: 'fresh-1'),
            $this->snapshot(status: FeedMatchStatus::Scheduled, externalId: 'fresh-2'),
        ]);

        self::assertCount(2, $result->created);
        self::assertSame([], $result->needsAdoption);
    }

    public function testResolvesTeamsThroughAliases(): void
    {
        $this->commandBus()->dispatch(new AddTeamAliasCommand(
            sportId: Uuid::fromString(Sport::FOOTBALL_ID),
            teamName: 'Sparta Praha',
            alias: 'AC Sparta Praha B',
        ));

        $teamsBefore = count($this->teams()->listGlobalBySport(Uuid::fromString(Sport::FOOTBALL_ID)));

        $result = $this->syncPublicSource([$this->scheduledSnapshot(homeTeamName: 'AC Sparta Praha B')]);

        self::assertCount(1, $result->created);
        self::assertSame([], $result->unresolvedTeams);

        $match = $this->findSynced();
        self::assertSame(AppFixtures::TEAM_SPARTA_ID, (string) $match->homeTeam->id);

        $teamsAfter = count($this->teams()->listGlobalBySport(Uuid::fromString(Sport::FOOTBALL_ID)));
        self::assertSame($teamsBefore, $teamsAfter);
    }

    public function testUnknownTeamParksMatchInsteadOfCreatingTeam(): void
    {
        $teamsBefore = count($this->teams()->listGlobalBySport(Uuid::fromString(Sport::FOOTBALL_ID)));

        $result = $this->syncPublicSource([$this->scheduledSnapshot(homeTeamName: 'FC Neexistuje')]);

        self::assertSame([], $result->created);
        self::assertSame(['FC Neexistuje'], $result->unresolvedTeams);
        self::assertNull($this->sportMatches()->findBySourceAndExternalId(
            Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            self::EXTERNAL_ID,
        ));

        $teamsAfter = count($this->teams()->listGlobalBySport(Uuid::fromString(Sport::FOOTBALL_ID)));
        self::assertSame($teamsBefore, $teamsAfter);
    }

    public function testKickoffMoveWithoutPostponeStatusKeepsScheduledState(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);

        $result = $this->syncPublicSource([
            $this->scheduledSnapshot(kickoffUtc: '2025-07-01 20:00:00 UTC'),
        ]);

        self::assertCount(1, $result->kickoffMoved);

        $match = $this->findSynced();
        self::assertSame(SportMatchState::Scheduled, $match->state);
        self::assertEquals(new \DateTimeImmutable('2025-07-01 20:00:00 UTC'), $match->kickoffAt);
    }

    public function testPostponeThenRescheduleFollowsFeedStatuses(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);

        $postponed = $this->syncPublicSource([
            $this->snapshot(status: FeedMatchStatus::Postponed, kickoffUtc: '2025-07-15 18:00:00 UTC'),
        ]);
        self::assertCount(1, $postponed->postponed);
        self::assertSame(SportMatchState::Postponed, $this->findSynced()->state);

        $rescheduled = $this->syncPublicSource([
            $this->scheduledSnapshot(kickoffUtc: '2025-07-15 18:00:00 UTC'),
        ]);
        self::assertCount(1, $rescheduled->rescheduled);

        $match = $this->findSynced();
        self::assertSame(SportMatchState::Scheduled, $match->state);
        self::assertEquals(new \DateTimeImmutable('2025-07-15 18:00:00 UTC'), $match->kickoffAt);
    }

    /**
     * The rule the whole live path exists to serve: a score on screen always
     * means the FINAL score. A zápas in progress is marked live and carries no
     * number, because 1:0 at half time is indistinguishable from 1:0 at the
     * whistle in every surface that renders it.
     */
    public function testLiveSnapshotMarksTheMatchWithoutPublishingItsScore(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);

        $result = $this->syncPublicSource([$this->snapshot(
            status: FeedMatchStatus::Live,
            homeScore: 1,
            awayScore: 0,
            periodScores: [[1, 0]],
        )]);

        self::assertCount(1, $result->liveUpdated);

        $match = $this->findSynced();
        self::assertSame(SportMatchState::Live, $match->state);
        self::assertNull($match->homeScore, 'a running score must never reach the database');
        self::assertNull($match->awayScore);
        self::assertNull($match->periodScores);
    }

    /**
     * Because nothing about a match in progress is ours to write, every later
     * poll of a live zápas is a genuine no-op — no writes, no events, no churn
     * for the five-minute cron to push through the notification machinery.
     */
    public function testFurtherLiveSnapshotsChangeNothing(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);
        $this->syncPublicSource([$this->snapshot(status: FeedMatchStatus::Live, homeScore: 1, awayScore: 0)]);

        $result = $this->syncPublicSource([$this->snapshot(
            status: FeedMatchStatus::Live,
            homeScore: 3,
            awayScore: 2,
        )]);

        self::assertSame(1, $result->unchanged);
        self::assertSame([], $result->liveUpdated);
        self::assertNull($this->findSynced()->homeScore);
    }

    /** …and the result lands in full the moment the feed calls it finished. */
    public function testTheScoreAppearsWhenTheMatchIsFinished(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);
        $this->syncPublicSource([$this->snapshot(status: FeedMatchStatus::Live, homeScore: 1, awayScore: 0)]);

        $result = $this->syncPublicSource([$this->finishedSnapshot()]);

        self::assertCount(1, $result->finished);

        $match = $this->findSynced();
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertSame(2, $match->homeScore);
        self::assertSame(1, $match->awayScore);
    }

    /**
     * We hold it postponed, the feed is playing it — our postponement is the
     * stale fact, so the fixture goes back on the calendar rather than getting
     * stuck off it until someone notices.
     */
    public function testAPostponedMatchTheFeedIsPlayingGoesBackOnTheCalendar(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);
        $this->syncPublicSource([$this->snapshot(
            status: FeedMatchStatus::Postponed,
            kickoffUtc: '2025-07-15 18:00:00 UTC',
        )]);

        $result = $this->syncPublicSource([$this->snapshot(
            status: FeedMatchStatus::Live,
            kickoffUtc: '2025-07-16 18:00:00 UTC',
            homeScore: 1,
            awayScore: 1,
        )]);

        self::assertCount(1, $result->liveUpdated);

        $match = $this->findSynced();
        self::assertSame(SportMatchState::Live, $match->state);
        self::assertEquals(new \DateTimeImmutable('2025-07-16 18:00:00 UTC'), $match->kickoffAt);
        self::assertNull($match->homeScore);
    }

    public function testFinishedSnapshotWritesScorePeriodsAndEvents(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);

        $result = $this->syncPublicSource([$this->finishedSnapshot()]);

        self::assertCount(1, $result->finished);

        $match = $this->findSynced();
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertSame(2, $match->homeScore);
        self::assertSame(1, $match->awayScore);
        self::assertSame([[1, 0], [1, 1]], $match->periodScores?->toArray());

        $events = $this->matchEvents()->listByMatch($match->id);
        self::assertCount(3, $events);
    }

    public function testRepeatedFinishedSyncIsNoOpAndKeepsEventRows(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);
        $this->syncPublicSource([$this->finishedSnapshot()]);

        $idsBefore = $this->eventIds();

        $result = $this->syncPublicSource([$this->finishedSnapshot()]);

        self::assertSame(1, $result->unchanged);
        self::assertSame([], $result->finished);
        self::assertSame([], $result->corrected);
        // The event sheet was not rewritten (replace() would mint new ids).
        self::assertSame($idsBefore, $this->eventIds());
    }

    public function testInitialFormPlayerNamesDoNotChurnTheEventSheet(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);
        $this->syncPublicSource([$this->finishedSnapshot()]);

        $idsBefore = $this->eventIds();

        // The same sheet, with the scorer abbreviated the way feeds often do.
        $abbreviated = $this->finishedSnapshot(events: [
            new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 12, 'K. Sparťan'),
            new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 51, 'K. Sparťan'),
            new MatchEventInput(MatchEventType::YellowCard, MatchSide::Away, 78, 'Pavel Sešívaný'),
        ]);

        $result = $this->syncPublicSource([$abbreviated]);

        self::assertSame(1, $result->unchanged);
        self::assertSame($idsBefore, $this->eventIds());
    }

    public function testScoreCorrectionWithoutEventsKeepsStoredEvents(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);
        $this->syncPublicSource([$this->finishedSnapshot()]);

        $idsBefore = $this->eventIds();
        self::assertCount(3, $idsBefore);

        $correction = $this->snapshot(
            status: FeedMatchStatus::Finished,
            homeScore: 3,
            awayScore: 1,
            events: null,
        );

        $result = $this->syncPublicSource([$correction]);

        self::assertCount(1, $result->corrected);

        $match = $this->findSynced();
        self::assertSame(3, $match->homeScore);
        // Score-only correction must not wipe the stored sheet.
        self::assertSame($idsBefore, $this->eventIds());
    }

    public function testCancellationIsReportedNotApplied(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);

        $result = $this->syncPublicSource([
            $this->snapshot(status: FeedMatchStatus::Cancelled),
        ]);

        self::assertCount(1, $result->cancelledReported);
        self::assertSame(SportMatchState::Scheduled, $this->findSynced()->state);
    }

    public function testDuplicateExternalIdRowsAreSkipped(): void
    {
        $result = $this->syncPublicSource([
            $this->scheduledSnapshot(),
            $this->scheduledSnapshot(kickoffUtc: '2025-07-02 18:00:00 UTC'),
        ]);

        self::assertCount(1, $result->created);
        self::assertCount(1, $result->errors);

        $match = $this->findSynced();
        self::assertEquals(new \DateTimeImmutable('2025-07-01 18:00:00 UTC'), $match->kickoffAt);
    }

    public function testUnknownStatusIsReportedAndSkipped(): void
    {
        $result = $this->syncPublicSource([
            $this->snapshot(status: FeedMatchStatus::Unknown, rawStatus: 'WEIRD'),
        ]);

        self::assertCount(1, $result->unknownStatus);
        self::assertNull($this->sportMatches()->findBySourceAndExternalId(
            Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            self::EXTERNAL_ID,
        ));
    }

    public function testDryRunReportsWithoutWriting(): void
    {
        $source = $this->bindPublicSource();

        /** @var FeedSynchronizer $synchronizer */
        $synchronizer = self::getContainer()->get(FeedSynchronizer::class);
        $result = $synchronizer->sync($source, [$this->scheduledSnapshot()], apply: false);

        self::assertTrue($result->dryRun);
        self::assertCount(1, $result->created);
        self::assertNull($this->sportMatches()->findBySourceAndExternalId(
            $source->id,
            self::EXTERNAL_ID,
        ));
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /**
     * @param list<MatchSnapshot> $snapshots
     */
    private function syncPublicSource(array $snapshots): FeedSyncResult
    {
        $source = $this->bindPublicSource();

        $envelope = $this->commandBus()->dispatch(new SyncMatchSourceFeedCommand(
            matchSourceId: $source->id,
            snapshots: $snapshots,
        ));

        $handled = $envelope->last(HandledStamp::class);
        $result = $handled?->getResult();
        self::assertInstanceOf(FeedSyncResult::class, $result);

        return $result;
    }

    private function bindPublicSource(): MatchSource
    {
        $source = $this->entityManager()->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $source);

        if (!$source->hasFeed) {
            $source->bindFeed(FeedProvider::Fixture, 'unused.json', new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
            $source->popEvents();
        }

        return $source;
    }

    private function scheduledSnapshot(
        string $homeTeamName = 'Sparta Praha',
        string $kickoffUtc = '2025-07-01 18:00:00 UTC',
    ): MatchSnapshot {
        return $this->snapshot(
            status: FeedMatchStatus::Scheduled,
            homeTeamName: $homeTeamName,
            kickoffUtc: $kickoffUtc,
        );
    }

    /**
     * @param list<MatchEventInput>|null $events
     */
    private function finishedSnapshot(?array $events = null): MatchSnapshot
    {
        return $this->snapshot(
            status: FeedMatchStatus::Finished,
            homeScore: 2,
            awayScore: 1,
            periodScores: [[1, 0], [1, 1]],
            events: $events ?? [
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 12, 'Karel Sparťan'),
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 51, 'Karel Sparťan'),
                new MatchEventInput(MatchEventType::YellowCard, MatchSide::Away, 78, 'Pavel Sešívaný'),
            ],
        );
    }

    /**
     * @param list<array{int, int}>|null $periodScores
     * @param list<MatchEventInput>|null $events
     */
    private function snapshot(
        FeedMatchStatus $status,
        string $homeTeamName = 'Sparta Praha',
        ?string $externalId = null,
        string $kickoffUtc = '2025-07-01 18:00:00 UTC',
        ?int $homeScore = null,
        ?int $awayScore = null,
        ?array $periodScores = null,
        ?array $events = null,
        ?string $rawStatus = null,
    ): MatchSnapshot {
        return new MatchSnapshot(
            externalId: $externalId ?? self::EXTERNAL_ID,
            homeTeamName: $homeTeamName,
            awayTeamName: 'Slavia Praha',
            kickoffUtc: new \DateTimeImmutable($kickoffUtc),
            status: $status,
            homeScore: $homeScore,
            awayScore: $awayScore,
            periodScores: $periodScores,
            events: $events,
            round: '1. kolo',
            venue: 'epet ARENA',
            rawStatus: $rawStatus,
        );
    }

    private function findSynced(): SportMatch
    {
        $match = $this->sportMatches()->findBySourceAndExternalId(
            Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            self::EXTERNAL_ID,
        );
        self::assertInstanceOf(SportMatch::class, $match);

        return $match;
    }

    /**
     * @return list<string>
     */
    private function eventIds(): array
    {
        $ids = array_map(
            static fn ($event): string => (string) $event->id,
            $this->matchEvents()->listByMatch($this->findSynced()->id),
        );
        sort($ids);

        return $ids;
    }

    private function sportMatches(): SportMatchRepository
    {
        /* @var SportMatchRepository */
        return self::getContainer()->get(SportMatchRepository::class);
    }

    private function matchEvents(): MatchEventRepository
    {
        /* @var MatchEventRepository */
        return self::getContainer()->get(MatchEventRepository::class);
    }

    private function teams(): TeamRepository
    {
        /* @var TeamRepository */
        return self::getContainer()->get(TeamRepository::class);
    }
}
