<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\LeaderboardTimeFilter;
use App\Query\GetCompetitionCurrentRound\CompetitionCurrentRoundResult;
use App\Query\GetCompetitionCurrentRound\GetCompetitionCurrentRound;
use App\Query\GetCompetitionLeaderboard\CompetitionLeaderboardResult;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Round („kolo") slicing of the leaderboard + the „what round are we in" read
 * side that the Žebříček hero stat consumes.
 *
 * Fixture baseline (clock 2025-06-15 12:00 UTC, PUBLIC_COMPETITION, mode All):
 *   MATCH_FINISHED  06-10 18:00  round „Základní skupina"  ← latest STARTED labelled match
 *   MATCH_LIVE      06-15 11:00  round NULL                 (started, unlabelled → skipped)
 *   MATCH_SCHEDULED 06-20 18:00  round „Čtvrtfinále"
 *   MATCH_PLAYOFF   06-22 18:00  round „Playoff"
 * ⇒ current round = „Základní skupina".
 */
final class GetCompetitionLeaderboardRoundTest extends IntegrationTestCase
{
    private const string PUBLIC_ID = AppFixtures::PUBLIC_COMPETITION_ID;

    public function testCurrentRoundIsTheLatestStartedLabelledMatch(): void
    {
        $result = $this->currentRound();

        self::assertSame('Základní skupina', $result->round);
        self::assertSame(1, $result->matchCount);
        self::assertSame(1, $result->finishedMatchCount);
    }

    public function testCurrentRoundSkipsUnlabelledMatchesRatherThanReportingAnEmptyRound(): void
    {
        // Give the (already started, later) live match a round of its own — it now
        // becomes the latest started LABELLED match, so it wins.
        $em = $this->entityManager();
        $live = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_LIVE_ID));
        self::assertNotNull($live);
        $live->updateDetails(null, null, null, null, $this->now(), round: 'Osmifinále');
        $live->popEvents();
        $em->flush();

        self::assertSame('Osmifinále', $this->currentRound()->round);
    }

    public function testCurrentRoundFallsBackToTheEarliestUpcomingRoundWhenNothingStarted(): void
    {
        // Strip the labels off everything already played ⇒ only future matches
        // carry a round; the earliest of those („Čtvrtfinále", 06-20) is current.
        $em = $this->entityManager();
        $finished = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finished);
        $finished->updateDetails(null, null, null, null, $this->now(), round: null);
        $finished->popEvents();
        $em->flush();

        self::assertSame('Čtvrtfinále', $this->currentRound()->round);
    }

    public function testLastRoundBoardCountsOnlyTheCurrentRoundsMatches(): void
    {
        // Admin already has 3 pts on MATCH_FINISHED („Základní skupina").
        // Add 5 more on a match in a DIFFERENT round — all-time must see 8, the
        // round-scoped board must still see only 3.
        $this->addEvaluatedGuess(AppFixtures::MATCH_SCHEDULED_ID, points: 5);

        $allTime = $this->leaderboard(LeaderboardTimeFilter::AllTime);
        self::assertSame(8, $allTime->rows[0]->totalPoints);
        self::assertNull($allTime->roundLabel, 'Only the round filter names a round.');

        $lastRound = $this->leaderboard(LeaderboardTimeFilter::LastRound);
        self::assertSame('Základní skupina', $lastRound->roundLabel);
        self::assertSame(3, $lastRound->rows[0]->totalPoints);
        self::assertSame(1, $lastRound->rows[0]->evaluatedCount);
        self::assertFalse($lastRound->showDelta, 'A re-ranked board carries no all-time Δ.');
    }

    public function testLastRoundBoardIgnoresUnlabelledMatches(): void
    {
        // MATCH_LIVE has no round at all — points on it belong to no round and
        // must never leak into the round-scoped board.
        $this->addEvaluatedGuess(AppFixtures::MATCH_LIVE_ID, points: 7);

        self::assertSame(10, $this->leaderboard(LeaderboardTimeFilter::AllTime)->rows[0]->totalPoints);
        self::assertSame(3, $this->leaderboard(LeaderboardTimeFilter::LastRound)->rows[0]->totalPoints);
    }

    public function testCompetitionWithoutAnyRoundReportsNoRoundAndLeavesTheBoardUnscoped(): void
    {
        // Clear every label in the source ⇒ there is no round to scope to. The
        // board must stay usable (= identical to Celkem) and say so via a null
        // roundLabel, so the UI can hide the tab instead of showing a lie.
        $em = $this->entityManager();
        foreach ([AppFixtures::MATCH_FINISHED_ID, AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_PLAYOFF_ID] as $id) {
            $match = $em->find(SportMatch::class, Uuid::fromString($id));
            self::assertNotNull($match);
            $match->updateDetails(null, null, null, null, $this->now(), round: null);
            $match->popEvents();
        }
        $em->flush();

        $currentRound = $this->currentRound();
        self::assertNull($currentRound->round);
        self::assertSame(0, $currentRound->matchCount);

        $lastRound = $this->leaderboard(LeaderboardTimeFilter::LastRound);
        self::assertNull($lastRound->roundLabel);
        self::assertSame(3, $lastRound->rows[0]->totalPoints, 'Unscoped fallback = the all-time total.');
    }

    /**
     * Acceptance criterion 4 of item 02: the round concept only SLICES scoring —
     * it must never move a „Celkem" total. Pins the all-time board of every
     * fixture competition that has an evaluated guess, including the per-user
     * stat columns the round filter also touches.
     */
    public function testAllTimeTotalsAreUnchangedByTheRoundFilter(): void
    {
        $publicBoard = $this->leaderboard(LeaderboardTimeFilter::AllTime);

        self::assertCount(1, $publicBoard->rows);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $publicBoard->rows[0]->nickname);
        self::assertSame(3, $publicBoard->rows[0]->totalPoints);
        self::assertSame(1, $publicBoard->rows[0]->rank);
        self::assertSame(1, $publicBoard->rows[0]->evaluatedCount);
        self::assertSame(1, $publicBoard->rows[0]->scoredCount);
        self::assertSame(0, $publicBoard->rows[0]->exactCount);
        self::assertSame(1, $publicBoard->rows[0]->partialCount);
        self::assertSame(100, $publicBoard->rows[0]->accuracyPercent);
        self::assertSame(1, $publicBoard->rows[0]->streak);
        self::assertTrue($publicBoard->showDelta);
        self::assertNull($publicBoard->roundLabel);

        // Rounds cover only part of the fixture schedule, so a round-blind total
        // must be ≥ the round-scoped one for the same board — never below it.
        $roundBoard = $this->leaderboard(LeaderboardTimeFilter::LastRound);
        self::assertGreaterThanOrEqual($roundBoard->rows[0]->totalPoints, $publicBoard->rows[0]->totalPoints);
    }

    private function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock()->now());
    }

    private function currentRound(): CompetitionCurrentRoundResult
    {
        return $this->queryBus()->handle(new GetCompetitionCurrentRound(
            competitionId: Uuid::fromString(self::PUBLIC_ID),
        ));
    }

    private function leaderboard(LeaderboardTimeFilter $filter): CompetitionLeaderboardResult
    {
        return $this->queryBus()->handle(new GetCompetitionLeaderboard(
            competitionId: Uuid::fromString(self::PUBLIC_ID),
            filter: $filter,
        ));
    }

    /**
     * Admin guesses the given match in PUBLIC_COMPETITION and the guess is
     * evaluated for `$points` (rule identity is irrelevant here — only the sum
     * and the owning match's round matter).
     */
    private function addEvaluatedGuess(string $matchId, int $points): void
    {
        $em = $this->entityManager();
        $now = $this->now();

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $competition = $em->find(Competition::class, Uuid::fromString(self::PUBLIC_ID));
        self::assertNotNull($competition);
        $match = $em->find(SportMatch::class, Uuid::fromString($matchId));
        self::assertInstanceOf(SportMatch::class, $match);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $admin,
            sportMatch: $match,
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
            points: $points,
        ));
        $em->persist($evaluation);

        $em->flush();
    }
}
