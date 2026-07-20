<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\BulkImportSportMatches\BulkImportSportMatchesCommand;
use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\Command\JoinCompetitionByPin\JoinCompetitionByPinCommand;
use App\Command\MarkSportMatchLive\MarkSportMatchLiveCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateLiveScore\UpdateLiveScoreCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\LeaderboardSnapshot;
use App\Entity\Notification;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Enum\MatchSourceKind;
use App\Enum\NotificationType;
use App\Enum\SportMatchState;
use App\Query\GetCompetitionLeaderboard\CompetitionLeaderboardResult;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Rule\PeriodExactRule;
use App\Rule\ScorerHitRule;
use App\Service\PragueCalendar;
use App\Service\SportMatch\SportMatchImportRow;
use App\Tests\Support\IntegrationTestCase;
use App\Value\GuessScorerInput;
use App\Value\MatchEventInput;
use App\Value\PeriodScores;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Uuid;

/**
 * E2E JOURNEY 1 — from-scratch hockey competition, whole as-built chain via the
 * command/query buses (no browser; Panther is not used — see CLAUDE.md).
 *
 *   wizard (from scratch, hockey, private source, PIN) → add matches (manual +
 *   CSV/XLSX import) → member joins via PIN → both members tip (owner with period
 *   + scorer tips) → manager pushes a live score then the final score with
 *   MatchEvents and „poslední zápas" (completes the source) → evaluations land →
 *   leaderboard + captured daily snapshot + delta → competition-ended notifications.
 *
 * Asserts the chain's end-state: per-guess points, standings, notification rows,
 * snapshot rows, and the next-day leaderboard delta.
 */
final class FullHappyPathTest extends IntegrationTestCase
{
    public function testFromScratchHockeyJourneyEndToEnd(): void
    {
        $em = $this->entityManager();
        $bus = $this->commandBus();

        $ownerId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);
        $memberId = Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID);

        // 1) Wizard: from-scratch hockey competition (PIN), optional period + scorer
        // rules turned on so members can tip thirds and scorers.
        $competitionId = $this->extractId($bus->dispatch(new CreateCompetitionCommand(
            ownerId: $ownerId,
            name: 'Hokejová tipovačka',
            matchSourceId: null,
            sportId: Uuid::fromString(Sport::HOCKEY_ID),
            fromScratch: true,
            withPin: true,
            monetization: CompetitionMonetization::None,
            ruleChanges: [
                PeriodExactRule::IDENTIFIER => ['enabled' => true, 'points' => 2],
                ScorerHitRule::IDENTIFIER => ['enabled' => true, 'points' => 2],
            ],
        )), Competition::class);

        $em->clear();
        $competition = $em->find(Competition::class, $competitionId);
        self::assertNotNull($competition);
        self::assertNotNull($competition->pin);
        self::assertSame(MatchSourceKind::Private, $competition->matchSource->kind);
        self::assertSame('hockey', $competition->matchSource->sport->code);
        $matchSourceId = $competition->matchSource->id;
        $pin = $competition->pin;

        // 2a) Add a match manually.
        $matchOneId = $this->extractId($bus->dispatch(new CreateSportMatchCommand(
            matchSourceId: $matchSourceId,
            editorId: $ownerId,
            homeTeam: 'HC Domácí',
            awayTeam: 'HC Hosté',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00', new \DateTimeZone('UTC')),
            venue: 'Zimní stadion',
        )), SportMatch::class);

        // 2b) Import a second match from a spreadsheet (CSV/XLSX pipeline).
        $imported = $bus->dispatch(new BulkImportSportMatchesCommand(
            matchSourceId: $matchSourceId,
            editorId: $ownerId,
            rows: [
                new SportMatchImportRow(
                    rowNumber: 1,
                    homeTeam: 'HC Alfa',
                    awayTeam: 'HC Beta',
                    kickoffAt: new \DateTimeImmutable('2025-06-20 20:00', new \DateTimeZone('UTC')),
                    venue: null,
                ),
            ],
        ))->last(HandledStamp::class)?->getResult();
        self::assertSame(1, $imported);
        $matchTwoId = $this->findMatchIdByHome($matchSourceId, 'HC Alfa');

        // 3) Member joins via PIN.
        $bus->dispatch(new JoinCompetitionByPinCommand(userId: $memberId, pin: $pin));

        // 4) Tips (kickoffs still in the future ⇒ tips open). Owner tips an EXACT
        // result with all three thirds and all three scorers; member tips a plain
        // (correct-outcome-only) score.
        $bus->dispatch(new SubmitGuessCommand(
            userId: $ownerId,
            competitionId: $competitionId,
            sportMatchId: $matchOneId,
            homeScore: 3,
            awayScore: 1,
            periodScores: PeriodScores::fromArray([[1, 0], [1, 1], [1, 0]]),
            scorers: [
                new GuessScorerInput(MatchSide::Home, 'Střelec A'),
                new GuessScorerInput(MatchSide::Home, 'Střelec B'),
                new GuessScorerInput(MatchSide::Away, 'Soupeř C'),
            ],
        ));
        $bus->dispatch(new SubmitGuessCommand(
            userId: $memberId,
            competitionId: $competitionId,
            sportMatchId: $matchOneId,
            homeScore: 2,
            awayScore: 0,
        ));

        // Move past the kickoffs — the competition has started (tips lock); results
        // now flow in.
        $this->mockClock()->modify('+6 days');

        // 5) Manager pushes a live score, then the final score of match one with a
        // goal timeline (the scorers the owner tipped) — three thirds (hockey).
        $bus->dispatch(new MarkSportMatchLiveCommand(sportMatchId: $matchOneId, editorId: $ownerId));
        $bus->dispatch(new UpdateLiveScoreCommand(
            sportMatchId: $matchOneId,
            editorId: $ownerId,
            homeScore: 2,
            awayScore: 1,
        ));
        $bus->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchOneId,
            editorId: $ownerId,
            homeScore: 3,
            awayScore: 1,
            periodScores: PeriodScores::fromArray([[1, 0], [1, 1], [1, 0]]),
            events: [
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 10, 'Střelec A'),
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 25, 'Střelec B'),
                new MatchEventInput(MatchEventType::Goal, MatchSide::Away, 40, 'Soupeř C'),
                new MatchEventInput(MatchEventType::Goal, MatchSide::Home, 55, 'Střelec A'),
            ],
        ));

        // 6) Final score of match two, ticking „poslední zápas" — completes the
        // source and (all matches finished+evaluated) fires competition-ended.
        $bus->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchTwoId,
            editorId: $ownerId,
            homeScore: 3,
            awayScore: 2,
            periodScores: PeriodScores::fromArray([[1, 0], [1, 1], [1, 1]]),
            isLastMatch: true,
        ));

        // 7) Evaluations: owner exact + 3 thirds + 3 scorers, member outcome only.
        $em->clear();
        // exact 5 + outcome 3 + home 1 + away 1 = 10; periods 3×2 = 6; scorers 3×2 = 6.
        self::assertSame(22, $this->evaluationPoints($ownerId, $matchOneId));
        self::assertSame(3, $this->evaluationPoints($memberId, $matchOneId));

        // 8) Source completed; both matches finished.
        $competition = $em->find(Competition::class, $competitionId);
        self::assertNotNull($competition);
        self::assertTrue($competition->matchSource->isCompleted);
        self::assertSame(SportMatchState::Finished, $this->matchState($matchOneId));
        self::assertSame(SportMatchState::Finished, $this->matchState($matchTwoId));

        // 9) Leaderboard standings.
        /** @var CompetitionLeaderboardResult $leaderboard */
        $leaderboard = $this->queryBus()->handle(new GetCompetitionLeaderboard(competitionId: $competitionId));
        self::assertCount(2, $leaderboard->rows);
        self::assertTrue($leaderboard->rows[0]->userId->equals($ownerId));
        self::assertSame(22, $leaderboard->rows[0]->totalPoints);
        self::assertSame(1, $leaderboard->rows[0]->rank);
        self::assertTrue($leaderboard->rows[1]->userId->equals($memberId));
        self::assertSame(3, $leaderboard->rows[1]->totalPoints);
        self::assertSame(2, $leaderboard->rows[1]->rank);
        self::assertTrue($leaderboard->matchSourceCompleted);

        // 10) Competition-ended notification: one per active member.
        $endedNotifications = $this->notificationsOfType($competitionId, NotificationType::CompetitionEnded);
        self::assertCount(2, $endedNotifications);
        $notifiedUserIds = array_map(static fn (Notification $n): string => $n->user->id->toRfc4122(), $endedNotifications);
        self::assertContains($ownerId->toRfc4122(), $notifiedUserIds);
        self::assertContains($memberId->toRfc4122(), $notifiedUserIds);

        // 11) Capture today's daily snapshot; rows mirror the live board.
        $today = PragueCalendar::day($this->mockClock()->now());
        $this->captureSnapshot($competitionId, $today);

        $snapshots = $this->snapshotsFor($competitionId);
        self::assertCount(2, $snapshots);
        self::assertSame(1, $snapshots[0]->rank);
        self::assertSame(22, $snapshots[0]->points);
        self::assertTrue($snapshots[0]->user->id->equals($ownerId));
        self::assertSame(2, $snapshots[1]->rank);
        self::assertSame(3, $snapshots[1]->points);

        // 12) Next day the leaderboard computes a delta against that snapshot —
        // nothing moved, so both are „beze změny" (delta 0) and not new.
        $this->mockClock()->modify('+1 day');
        /** @var CompetitionLeaderboardResult $nextDay */
        $nextDay = $this->queryBus()->handle(new GetCompetitionLeaderboard(competitionId: $competitionId));
        self::assertTrue($nextDay->showDelta);
        self::assertSame(0, $nextDay->rows[0]->delta);
        self::assertFalse($nextDay->rows[0]->deltaIsNew);
        self::assertSame(0, $nextDay->rows[1]->delta);
    }

    private function evaluationPoints(Uuid $userId, Uuid $matchId): int
    {
        $guess = $this->entityManager()->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u AND g.sportMatch = :m')
            ->setParameter('u', $userId)
            ->setParameter('m', $matchId)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);

        $evaluation = $this->entityManager()->createQueryBuilder()
            ->select('e')->from(GuessEvaluation::class, 'e')
            ->where('e.guess = :g')
            ->setParameter('g', $guess->id)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(GuessEvaluation::class, $evaluation);

        return $evaluation->totalPoints;
    }

    private function matchState(Uuid $matchId): SportMatchState
    {
        $match = $this->entityManager()->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);

        return $match->state;
    }

    private function findMatchIdByHome(Uuid $matchSourceId, string $homeTeam): Uuid
    {
        $match = $this->entityManager()->createQueryBuilder()
            ->select('m')->from(SportMatch::class, 'm')
            ->where('m.matchSource = :s AND m.homeTeam = :h')
            ->setParameter('s', $matchSourceId)
            ->setParameter('h', $homeTeam)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(SportMatch::class, $match);

        return $match->id;
    }

    /**
     * @return list<Notification>
     */
    private function notificationsOfType(Uuid $competitionId, NotificationType $type): array
    {
        /** @var list<Notification> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('n', 'u')->from(Notification::class, 'n')
            ->innerJoin('n.user', 'u')
            ->where('n.competition = :c AND n.type = :t')
            ->setParameter('c', $competitionId)
            ->setParameter('t', $type)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function captureSnapshot(Uuid $competitionId, \DateTimeImmutable $day): void
    {
        // The command is async-routed; ReceivedStamp handles it locally with the
        // full command-bus middleware (doctrine_transaction flush).
        $this->commandBus()->dispatch(new Envelope(
            new \App\Command\CaptureLeaderboardSnapshots\CaptureLeaderboardSnapshotsCommand(
                competitionId: $competitionId,
                day: $day,
            ),
            [new ReceivedStamp('async')],
        ));
    }

    /**
     * @return list<LeaderboardSnapshot>
     */
    private function snapshotsFor(Uuid $competitionId): array
    {
        $em = $this->entityManager();
        $em->clear();

        /** @var list<LeaderboardSnapshot> $rows */
        $rows = $em->createQueryBuilder()
            ->select('s', 'u')->from(LeaderboardSnapshot::class, 's')
            ->innerJoin('s.user', 'u')
            ->where('s.competition = :c')
            ->setParameter('c', $competitionId)
            ->orderBy('s.rank', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function mockClock(): MockClock
    {
        $clock = $this->clock();
        self::assertInstanceOf(MockClock::class, $clock);

        return $clock;
    }

    private function extractId(Envelope $envelope, string $expectedClass): Uuid
    {
        $entity = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf($expectedClass, $entity);

        return $entity->id;
    }
}
