<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\CaptureLeaderboardSnapshots\CaptureLeaderboardSnapshotsCommand;
use App\Command\RecalculateCompetitionPoints\RecalculateCompetitionPointsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\LeaderboardSnapshot;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\LeaderboardTimeFilter;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\GetCompetitionLeaderboard\LeaderboardRow;
use App\Service\PragueCalendar;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Uuid;

final class GetCompetitionLeaderboardDeltaTest extends IntegrationTestCase
{
    private const string PUBLIC_ID = AppFixtures::PUBLIC_COMPETITION_ID;

    public function testNoSnapshotHistoryYieldsNullDeltaButShowsColumn(): void
    {
        $result = $this->leaderboard(LeaderboardTimeFilter::AllTime);

        self::assertTrue($result->showDelta, 'All-time always shows the Δ column.');
        self::assertCount(1, $result->rows);
        self::assertNull($result->rows[0]->delta, 'No history ⇒ neutral (null) delta.');
        self::assertFalse($result->rows[0]->deltaIsNew);
    }

    public function testClimbFallAndNewMemberDeltas(): void
    {
        // Current standings: VERIFIED 10 (rank 1), ADMIN 3 (rank 2), SECOND 0 (rank 3).
        $this->addMemberWithExactHit(AppFixtures::VERIFIED_USER_ID); // 10 pts on MATCH_FINISHED
        $this->addMember(AppFixtures::SECOND_VERIFIED_USER_ID);      // no guess ⇒ 0 pts

        // Yesterday (2025-06-14): ADMIN 1st, VERIFIED 3rd, SECOND absent.
        $this->seedSnapshot('2025-06-14', AppFixtures::ADMIN_ID, rank: 1, points: 20);
        $this->seedSnapshot('2025-06-14', AppFixtures::VERIFIED_USER_ID, rank: 3, points: 4);

        $rows = $this->rowsByUser($this->leaderboard(LeaderboardTimeFilter::AllTime));

        // VERIFIED climbed 3 → 1 (+2); ADMIN fell 1 → 2 (−1); SECOND is new.
        self::assertSame(2, $rows[AppFixtures::VERIFIED_USER_ID]->delta);
        self::assertFalse($rows[AppFixtures::VERIFIED_USER_ID]->deltaIsNew);
        self::assertSame(-1, $rows[AppFixtures::ADMIN_ID]->delta);
        self::assertTrue($rows[AppFixtures::SECOND_VERIFIED_USER_ID]->deltaIsNew);
        self::assertNull($rows[AppFixtures::SECOND_VERIFIED_USER_ID]->delta);
    }

    public function testDeltaUsesLatestDayStrictlyBeforeToday(): void
    {
        // A today (2025-06-15) snapshot must be ignored for today's Δ; only the
        // latest day strictly before counts.
        $this->seedSnapshot('2025-06-14', AppFixtures::ADMIN_ID, rank: 5, points: 1);
        $this->seedSnapshot('2025-06-15', AppFixtures::ADMIN_ID, rank: 1, points: 3);

        $rows = $this->rowsByUser($this->leaderboard(LeaderboardTimeFilter::AllTime));

        // ADMIN is sole member ⇒ current rank 1; baseline is 2025-06-14 rank 5 ⇒ +4.
        self::assertSame(4, $rows[AppFixtures::ADMIN_ID]->delta);
    }

    public function testRecalculationReCapturesTodayOnlyLeavingHistory(): void
    {
        // History baseline that must survive the recalc.
        $this->seedSnapshot('2025-06-14', AppFixtures::ADMIN_ID, rank: 1, points: 50);

        // Corrupt the evaluation, capture today off the corrupted value (99).
        $this->corruptFixtureEvaluation(99);
        $this->capture('2025-06-15');
        self::assertSame(99, $this->snapshotPoints('2025-06-15', AppFixtures::ADMIN_ID));

        // Recalc rebuilds the evaluation from the guess (3:0 vs 2:1 ⇒ 3) and its
        // CompetitionPointsRecalculated event re-captures TODAY only.
        $this->recalculateAndHandleCaptures();

        self::assertSame(3, $this->snapshotPoints('2025-06-15', AppFixtures::ADMIN_ID), 'Today re-captured with corrected points.');
        self::assertSame(50, $this->snapshotPoints('2025-06-14', AppFixtures::ADMIN_ID), 'Yesterday untouched.');
    }

    public function testLast7DaysWindowExcludesOlderEvaluations(): void
    {
        // ADMIN keeps the fixture eval (MATCH_FINISHED, 2025-06-10, 5 days ago,
        // in window, 3 pts) and gains an eval on a match 10 days ago (out of window).
        $this->addOldEvaluatedGuess(AppFixtures::ADMIN_ID, kickoff: '2025-06-05 18:00:00', points: 5);

        $allTime = $this->rowsByUser($this->leaderboard(LeaderboardTimeFilter::AllTime));
        self::assertSame(8, $allTime[AppFixtures::ADMIN_ID]->totalPoints, 'Celkem = 3 + 5.');

        $window = $this->leaderboard(LeaderboardTimeFilter::Last7Days);
        self::assertFalse($window->showDelta, 'Windowed board hides the Δ column.');
        self::assertSame(3, $this->rowsByUser($window)[AppFixtures::ADMIN_ID]->totalPoints, 'Only the in-window match counts.');
    }

    private function leaderboard(LeaderboardTimeFilter $filter): \App\Query\GetCompetitionLeaderboard\CompetitionLeaderboardResult
    {
        return $this->queryBus()->handle(new GetCompetitionLeaderboard(
            competitionId: Uuid::fromString(self::PUBLIC_ID),
            filter: $filter,
        ));
    }

    /**
     * @return array<string, LeaderboardRow>
     */
    private function rowsByUser(\App\Query\GetCompetitionLeaderboard\CompetitionLeaderboardResult $result): array
    {
        $map = [];

        foreach ($result->rows as $row) {
            $map[$row->userId->toRfc4122()] = $row;
        }

        return $map;
    }

    private function addMember(string $userId): void
    {
        $em = $this->entityManager();
        $membership = new Membership(
            id: Uuid::v7(),
            competition: $this->competition(),
            user: $this->user($userId),
            joinedAt: $this->now(),
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();
    }

    private function addMemberWithExactHit(string $userId): void
    {
        $em = $this->entityManager();
        $competition = $this->competition();
        $finishedMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finishedMatch);

        $membership = new Membership(id: Uuid::v7(), competition: $competition, user: $this->user($userId), joinedAt: $this->now());
        $membership->popEvents();
        $em->persist($membership);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $this->user($userId),
            sportMatch: $finishedMatch,
            competition: $competition,
            homeScore: 2,
            awayScore: 1,
            submittedAt: $this->now(),
        );
        $guess->popEvents();
        $em->persist($guess);

        // Exact 2:1 ⇒ all four PUBLIC rules hit = 5 + 3 + 1 + 1 = 10.
        $evaluation = new GuessEvaluation(id: Uuid::v7(), guess: $guess, evaluatedAt: $this->now());
        foreach (['exact_score' => 5, 'correct_outcome' => 3, 'correct_home_goals' => 1, 'correct_away_goals' => 1] as $rule => $points) {
            $evaluation->addRulePoints(new GuessEvaluationRulePoints(
                id: Uuid::v7(),
                evaluation: $evaluation,
                ruleIdentifier: $rule,
                points: $points,
            ));
        }
        $em->persist($evaluation);
        $em->flush();
    }

    private function addOldEvaluatedGuess(string $userId, string $kickoff, int $points): void
    {
        $em = $this->entityManager();
        $competition = $this->competition();

        $match = new SportMatch(
            id: Uuid::v7(),
            matchSource: $competition->matchSource,
            homeTeam: 'Staré A',
            awayTeam: 'Staré B',
            kickoffAt: new \DateTimeImmutable($kickoff, new \DateTimeZone('UTC')),
            venue: null,
            createdAt: $this->now(),
        );
        $match->popEvents();
        $em->persist($match);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $this->user($userId),
            sportMatch: $match,
            competition: $competition,
            homeScore: 1,
            awayScore: 0,
            submittedAt: $this->now(),
        );
        $guess->popEvents();
        $em->persist($guess);

        $evaluation = new GuessEvaluation(id: Uuid::v7(), guess: $guess, evaluatedAt: $this->now());
        $evaluation->addRulePoints(new GuessEvaluationRulePoints(
            id: Uuid::v7(),
            evaluation: $evaluation,
            ruleIdentifier: 'exact_score',
            points: $points,
        ));
        $em->persist($evaluation);
        $em->flush();
    }

    private function seedSnapshot(string $day, string $userId, int $rank, int $points): void
    {
        $em = $this->entityManager();
        $em->persist(new LeaderboardSnapshot(
            id: Uuid::v7(),
            competition: $this->competition(),
            user: $this->user($userId),
            day: new \DateTimeImmutable($day.' 00:00:00', PragueCalendar::timezone()),
            points: $points,
            rank: $rank,
            createdAt: $this->now(),
        ));
        $em->flush();
    }

    private function capture(string $day): void
    {
        $this->commandBus()->dispatch(new Envelope(
            new CaptureLeaderboardSnapshotsCommand(
                competitionId: Uuid::fromString(self::PUBLIC_ID),
                day: new \DateTimeImmutable($day.' 00:00:00', PragueCalendar::timezone()),
            ),
            [new ReceivedStamp('async')],
        ));
    }

    private function recalculateAndHandleCaptures(): void
    {
        $async = $this->messengerAsyncTransport();
        $async->reset();

        $this->commandBus()->dispatch(new Envelope(
            new RecalculateCompetitionPointsCommand(competitionId: Uuid::fromString(self::PUBLIC_ID)),
            [new ReceivedStamp('async')],
        ));

        foreach ($async->getSent() as $envelope) {
            $message = $envelope->getMessage();

            if ($message instanceof CaptureLeaderboardSnapshotsCommand) {
                $this->commandBus()->dispatch(new Envelope($message, [new ReceivedStamp('async')]));
            }
        }
    }

    private function corruptFixtureEvaluation(int $points): void
    {
        $this->entityManager()->createQuery(
            'UPDATE App\Entity\GuessEvaluation e SET e.totalPoints = :points WHERE e.id = :id',
        )->execute(['points' => $points, 'id' => Uuid::fromString(AppFixtures::FIXTURE_GUESS_EVALUATION_ID)]);
    }

    private function snapshotPoints(string $day, string $userId): ?int
    {
        $em = $this->entityManager();
        $em->clear();

        /** @var ?LeaderboardSnapshot $snapshot */
        $snapshot = $em->createQueryBuilder()
            ->select('s')
            ->from(LeaderboardSnapshot::class, 's')
            ->where('s.competition = :competitionId')
            ->andWhere('s.user = :userId')
            ->andWhere('s.day = :day')
            ->setParameter('competitionId', Uuid::fromString(self::PUBLIC_ID))
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('day', new \DateTimeImmutable($day.' 00:00:00', PragueCalendar::timezone()), \Doctrine\DBAL\Types\Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();

        return $snapshot?->points;
    }

    private function competition(): Competition
    {
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(self::PUBLIC_ID));
        self::assertNotNull($competition);

        return $competition;
    }

    private function user(string $userId): User
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString($userId));
        self::assertNotNull($user);

        return $user;
    }

    private function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock()->now());
    }
}
