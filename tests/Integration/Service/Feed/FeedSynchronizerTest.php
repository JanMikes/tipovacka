<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Feed;

use App\Command\AddTeamAlias\AddTeamAliasCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
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
use App\Value\PeriodScores;
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

    /**
     * No real provider publishes a shootout winner or a period breakdown, so a
     * null there means „unknown", not „none". Before this, every poll of a
     * score-only feed wiped what the admin had entered by hand and re-evaluated
     * the whole match — and never converged.
     */
    public function testAHandEnteredWinnerAndPeriodsSurviveScoreOnlyPolls(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);
        $draw = $this->snapshot(status: FeedMatchStatus::Finished, homeScore: 2, awayScore: 2);
        $this->syncPublicSource([$draw]);

        // The admin records what the feed cannot: the winner after penalties and the halves.
        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $this->findSynced()->id,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 2,
            awayScore: 2,
            periodScores: PeriodScores::fromArray([[1, 1], [1, 1]]),
            overtimeHomeScore: 3,
            overtimeAwayScore: 2,
            events: null,
        ));

        $result = $this->syncPublicSource([$draw]);

        self::assertSame(1, $result->unchanged);
        self::assertSame([], $result->corrected);
        $match = $this->findSynced();
        self::assertSame(MatchSide::Home, $match->overtimeWinner);
        self::assertSame([[1, 1], [1, 1]], $match->periodScores?->toArray());

        // A corrected draw keeps the winner (re-derived for 1:1) but not halves that no longer add up.
        $this->syncPublicSource([$this->snapshot(status: FeedMatchStatus::Finished, homeScore: 1, awayScore: 1)]);
        $match = $this->findSynced();
        self::assertSame([1, 1, 2, 1], [$match->homeScore, $match->awayScore, $match->overtimeHomeScore, $match->overtimeAwayScore]);
        self::assertNull($match->periodScores);

        // The feed says it was no draw after all: the winner is gone.
        $this->syncPublicSource([$this->snapshot(status: FeedMatchStatus::Finished, homeScore: 2, awayScore: 1)]);
        self::assertNull($this->findSynced()->overtimeWinner);
    }

    /**
     * The UEFA adapter now derives the winner from the payload, so a snapshot
     * CAN carry the pair. On a zdroj that plays prodloužení it is simply the
     * result — and the second poll of the same snapshot has to be a no-op, or
     * every five minutes would delete and recompute the whole match's
     * evaluations.
     */
    public function testAFeedReportedOvertimeWinnerIsStoredAndThenLeftAlone(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);

        $decided = $this->snapshot(
            status: FeedMatchStatus::Finished,
            homeScore: 2,
            awayScore: 2,
            overtimeHomeScore: 2,
            overtimeAwayScore: 3,
        );

        $result = $this->syncPublicSource([$decided]);

        self::assertCount(1, $result->finished);
        self::assertSame([], $result->overtimeNotPlayed);
        $match = $this->findSynced();
        self::assertSame([2, 2, 2, 3], [$match->homeScore, $match->awayScore, $match->overtimeHomeScore, $match->overtimeAwayScore]);
        self::assertSame(MatchSide::Away, $match->overtimeWinner);

        $again = $this->syncPublicSource([$decided]);

        self::assertSame(1, $again->unchanged);
        self::assertSame([], $again->corrected);
    }

    /**
     * A curated zdroj whose „hraje se prodloužení" is off but whose feed serves
     * knockout ties — the UEFA competitions serve both. The entity refuses the
     * pair outright, so without dropping it here the source would throw on every
     * one of its 288 daily polls. It is dropped, the score lands, and the report
     * says so once — as a WARNING: the cron must not fail over a checkbox.
     */
    public function testAWinnerIsDroppedOnAZdrojThatPlaysNoOvertimeAndReportedOnce(): void
    {
        $this->syncPublicSource([$this->scheduledSnapshot()]);
        $this->withoutOvertime();

        $decided = $this->snapshot(
            status: FeedMatchStatus::Finished,
            homeScore: 1,
            awayScore: 1,
            overtimeHomeScore: 2,
            overtimeAwayScore: 1,
        );

        $result = $this->syncPublicSource([$decided]);

        self::assertSame([], $result->errors, 'a checkbox must never fail the batch');
        self::assertFalse($result->hasFailures);
        self::assertTrue($result->hasWarnings);
        self::assertCount(1, $result->overtimeNotPlayed);
        self::assertCount(1, $result->finished);

        $match = $this->findSynced();
        self::assertSame([1, 1], [$match->homeScore, $match->awayScore]);
        self::assertNull($match->overtimeWinner);

        // Said once, when the result landed — the next poll is a plain no-op.
        $again = $this->syncPublicSource([$decided]);

        self::assertSame([], $again->overtimeNotPlayed);
        self::assertSame([], $again->corrected);
        self::assertSame(1, $again->unchanged);
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
        ?int $overtimeHomeScore = null,
        ?int $overtimeAwayScore = null,
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
            overtimeHomeScore: $overtimeHomeScore,
            overtimeAwayScore: $overtimeAwayScore,
            events: $events,
            round: '1. kolo',
            venue: 'epet ARENA',
            rawStatus: $rawStatus,
        );
    }

    /** Turns „hraje se prodloužení" off on the fixture source (it ships on). */
    private function withoutOvertime(): void
    {
        $source = $this->bindPublicSource();
        $source->updateDetails(
            name: $source->name,
            description: $source->description,
            startAt: $source->startAt,
            endAt: $source->endAt,
            hasOvertime: false,
            now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $source->popEvents();
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
