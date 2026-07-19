<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CaptureLeaderboardSnapshots\CaptureLeaderboardSnapshotsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\LeaderboardSnapshot;
use App\Entity\LeaderboardTieResolution;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Service\PragueCalendar;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Uuid;

final class CaptureLeaderboardSnapshotsHandlerTest extends IntegrationTestCase
{
    private const string PUBLIC_ID = AppFixtures::PUBLIC_COMPETITION_ID;

    public function testCapturesOneRowPerMemberWithRankAndPoints(): void
    {
        $day = new \DateTimeImmutable('2025-06-15 00:00:00', PragueCalendar::timezone());

        $this->capture(self::PUBLIC_ID, $day);

        $snapshots = $this->loadSnapshots(self::PUBLIC_ID);

        // Sole evaluated member: ADMIN, 3 points, rank 1.
        self::assertCount(1, $snapshots);
        self::assertSame(1, $snapshots[0]->rank);
        self::assertSame(3, $snapshots[0]->points);
        self::assertSame('2025-06-15', $snapshots[0]->day->format('Y-m-d'));
    }

    public function testReRunReplacesTheDayWithoutDuplicates(): void
    {
        $day = new \DateTimeImmutable('2025-06-15 00:00:00', PragueCalendar::timezone());

        $this->capture(self::PUBLIC_ID, $day);
        $this->capture(self::PUBLIC_ID, $day);

        // The unique (competition, user, day) index is honoured — a re-run upserts,
        // never piles up a second row for the same member/day.
        self::assertCount(1, $this->loadSnapshots(self::PUBLIC_ID));
    }

    public function testReCaptureReflectsCorrectedPoints(): void
    {
        $day = new \DateTimeImmutable('2025-06-15 00:00:00', PragueCalendar::timezone());

        $this->capture(self::PUBLIC_ID, $day);
        self::assertSame(3, $this->loadSnapshots(self::PUBLIC_ID)[0]->points);

        // Correct the fixture evaluation, then re-capture the same day.
        $this->entityManager()->createQuery(
            'UPDATE App\Entity\GuessEvaluation e SET e.totalPoints = 42 WHERE e.id = :id',
        )->execute(['id' => Uuid::fromString(AppFixtures::FIXTURE_GUESS_EVALUATION_ID)]);

        $this->capture(self::PUBLIC_ID, $day);

        $snapshots = $this->loadSnapshots(self::PUBLIC_ID);
        self::assertCount(1, $snapshots);
        self::assertSame(42, $snapshots[0]->points);
    }

    public function testCapturesTieResolutionOverrideRank(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $competition = $em->find(Competition::class, Uuid::fromString(self::PUBLIC_ID));
        self::assertNotNull($competition);
        $finishedMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finishedMatch);

        // VERIFIED joins and ties ADMIN on 3 points (both correct outcome only).
        $membership = new Membership(id: Uuid::v7(), competition: $competition, user: $verified, joinedAt: $now);
        $membership->popEvents();
        $em->persist($membership);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $verified,
            sportMatch: $finishedMatch,
            competition: $competition,
            homeScore: 1,
            awayScore: 0,
            submittedAt: $now,
        );
        $guess->popEvents();
        $em->persist($guess);

        $evaluation = new GuessEvaluation(id: Uuid::v7(), guess: $guess, evaluatedAt: $now);
        $evaluation->addRulePoints(new GuessEvaluationRulePoints(
            id: Uuid::v7(),
            evaluation: $evaluation,
            ruleIdentifier: 'correct_outcome',
            points: 3,
        ));
        $em->persist($evaluation);

        // Manager breaks the tie: ADMIN 1st, VERIFIED 2nd.
        $em->persist(new LeaderboardTieResolution(
            id: Uuid::v7(),
            competition: $competition,
            user: $admin,
            rank: 1,
            resolvedAt: $now,
            resolvedBy: $admin,
        ));
        $em->persist(new LeaderboardTieResolution(
            id: Uuid::v7(),
            competition: $competition,
            user: $verified,
            rank: 2,
            resolvedAt: $now,
            resolvedBy: $admin,
        ));
        $em->flush();

        $this->capture(self::PUBLIC_ID, new \DateTimeImmutable('2025-06-15 00:00:00', PragueCalendar::timezone()));

        $byUser = [];
        foreach ($this->loadSnapshots(self::PUBLIC_ID) as $snapshot) {
            $byUser[$snapshot->user->id->toRfc4122()] = $snapshot;
        }

        // The captured rank is the tie-resolved override, not the computed tie (both rank 1).
        self::assertSame(1, $byUser[AppFixtures::ADMIN_ID]->rank);
        self::assertSame(2, $byUser[AppFixtures::VERIFIED_USER_ID]->rank);
    }

    /**
     * A capture „today" run just after the Prague midnight rollover (still the
     * previous UTC day) must land on the Prague date. Clock at 2025-06-14 22:30
     * UTC = 2025-06-15 00:30 Prague ⇒ the snapshot day is 2025-06-15, not the UTC
     * 2025-06-14.
     */
    public function testCaptureTodayUsesPragueDateAcrossMidnightBoundary(): void
    {
        // Fixed clock 2025-06-15 12:00 UTC ⇒ back 13 h 30 m = 2025-06-14 22:30 UTC.
        $clock = $this->mockClock();
        $clock->modify('-13 hours -30 minutes');

        $pragueToday = PragueCalendar::day($clock->now());
        self::assertSame('2025-06-15', $pragueToday->format('Y-m-d'));

        $this->capture(self::PUBLIC_ID, $pragueToday);

        self::assertSame('2025-06-15', $this->loadSnapshots(self::PUBLIC_ID)[0]->day->format('Y-m-d'));
    }

    private function capture(string $competitionId, \DateTimeImmutable $day): void
    {
        // ReceivedStamp handles the async-routed command locally with full
        // command-bus middleware (doctrine_transaction flush).
        $this->commandBus()->dispatch(new Envelope(
            new CaptureLeaderboardSnapshotsCommand(competitionId: Uuid::fromString($competitionId), day: $day),
            [new ReceivedStamp('async')],
        ));
    }

    /**
     * @return list<LeaderboardSnapshot>
     */
    private function loadSnapshots(string $competitionId): array
    {
        $em = $this->entityManager();
        $em->clear();

        /** @var list<LeaderboardSnapshot> $rows */
        $rows = $em->createQueryBuilder()
            ->select('s', 'u')
            ->from(LeaderboardSnapshot::class, 's')
            ->innerJoin('s.user', 'u')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->orderBy('s.rank', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function mockClock(): \Symfony\Component\Clock\MockClock
    {
        $clock = $this->clock();
        self::assertInstanceOf(\Symfony\Component\Clock\MockClock::class, $clock);

        return $clock;
    }
}
