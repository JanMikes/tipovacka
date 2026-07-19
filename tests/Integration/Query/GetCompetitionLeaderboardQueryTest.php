<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\LeaderboardTieResolution;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetCompetitionLeaderboardQueryTest extends IntegrationTestCase
{
    public function testAdminHasThreePointsAsSoleEvaluatedMember(): void
    {
        $result = $this->queryBus()->handle(new GetCompetitionLeaderboard(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        self::assertCount(1, $result->rows);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $result->rows[0]->nickname);
        self::assertNull($result->rows[0]->fullName, 'Admin has no fullName set, so subtitle stays null.');
        self::assertSame(3, $result->rows[0]->totalPoints);
        self::assertSame(1, $result->rows[0]->rank);
        self::assertFalse($result->rows[0]->isTieResolvedOverride);
        self::assertFalse($result->matchSourceCompleted);

        // Stats: admin's single evaluated guess scored (correct outcome = 3 b, not exact).
        self::assertSame(1, $result->rows[0]->evaluatedCount);
        self::assertSame(1, $result->rows[0]->scoredCount);
        self::assertSame(0, $result->rows[0]->exactCount);
        self::assertSame(1, $result->rows[0]->partialCount);
        self::assertSame(100, $result->rows[0]->accuracyPercent);
        self::assertSame(1, $result->rows[0]->streak);
    }

    public function testFullNameSubtitleIsSetWhenUserHasBothNicknameAndFullName(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $verified->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: $now);

        $publicCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($publicCompetition);

        $membership = new Membership(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $result = $this->queryBus()->handle(new GetCompetitionLeaderboard(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        $verifiedRow = null;
        foreach ($result->rows as $row) {
            if (AppFixtures::VERIFIED_USER_NICKNAME === $row->nickname) {
                $verifiedRow = $row;
            }
        }

        self::assertNotNull($verifiedRow);
        self::assertSame('Jan Tipař', $verifiedRow->fullName);
    }

    public function testFullNameSubtitleIsNullWhenUserHasNoNickname(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        // Anonymous fixture has firstName+lastName but no nickname — the row should
        // display fullName as primary (no subtitle needed).
        $anonymous = $em->find(User::class, Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID));
        self::assertNotNull($anonymous);

        $publicCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($publicCompetition);

        $membership = new Membership(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $anonymous,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $result = $this->queryBus()->handle(new GetCompetitionLeaderboard(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        $anonymousRow = null;
        foreach ($result->rows as $row) {
            if ($row->userId->equals(Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID))) {
                $anonymousRow = $row;
            }
        }

        self::assertNotNull($anonymousRow);
        self::assertSame(
            AppFixtures::ANONYMOUS_USER_FIRST_NAME.' '.AppFixtures::ANONYMOUS_USER_LAST_NAME,
            $anonymousRow->nickname,
            'With no nickname, displayName falls back to fullName as the primary text.',
        );
        self::assertNull($anonymousRow->fullName, 'No subtitle when there is no separate nickname.');
    }

    public function testTwoTiedMembersShareRankOne(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);

        $publicCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($publicCompetition);

        $finishedMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finishedMatch);

        $membership = new Membership(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $verified,
            sportMatch: $finishedMatch,
            competition: $publicCompetition,
            homeScore: 1,
            awayScore: 0,
            submittedAt: $now,
        );
        $guess->popEvents();
        $em->persist($guess);

        $evaluation = new GuessEvaluation(
            id: Uuid::v7(),
            guess: $guess,
            evaluatedAt: $now,
        );
        $evaluation->addRulePoints(new GuessEvaluationRulePoints(
            id: Uuid::v7(),
            evaluation: $evaluation,
            ruleIdentifier: 'correct_outcome',
            points: 3,
        ));
        $em->persist($evaluation);

        $em->flush();

        $result = $this->queryBus()->handle(new GetCompetitionLeaderboard(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        self::assertCount(2, $result->rows);
        self::assertSame(1, $result->rows[0]->rank);
        self::assertSame(1, $result->rows[1]->rank);
        self::assertSame(3, $result->rows[0]->totalPoints);
        self::assertSame(3, $result->rows[1]->totalPoints);
    }

    public function testTieResolutionOverridesComputedRanks(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);

        $publicCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($publicCompetition);

        $finishedMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finishedMatch);

        $membership = new Membership(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $verified,
            sportMatch: $finishedMatch,
            competition: $publicCompetition,
            homeScore: 1,
            awayScore: 0,
            submittedAt: $now,
        );
        $guess->popEvents();
        $em->persist($guess);

        $evaluation = new GuessEvaluation(
            id: Uuid::v7(),
            guess: $guess,
            evaluatedAt: $now,
        );
        $evaluation->addRulePoints(new GuessEvaluationRulePoints(
            id: Uuid::v7(),
            evaluation: $evaluation,
            ruleIdentifier: 'correct_outcome',
            points: 3,
        ));
        $em->persist($evaluation);

        $em->persist(new LeaderboardTieResolution(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $admin,
            rank: 1,
            resolvedAt: $now,
            resolvedBy: $admin,
        ));
        $em->persist(new LeaderboardTieResolution(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $verified,
            rank: 2,
            resolvedAt: $now,
            resolvedBy: $admin,
        ));

        $em->flush();

        $result = $this->queryBus()->handle(new GetCompetitionLeaderboard(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        self::assertCount(2, $result->rows);
        self::assertSame(1, $result->rows[0]->rank);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $result->rows[0]->nickname);
        self::assertTrue($result->rows[0]->isTieResolvedOverride);
        self::assertSame(2, $result->rows[1]->rank);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $result->rows[1]->nickname);
        self::assertTrue($result->rows[1]->isTieResolvedOverride);
    }

    public function testSubsetLeaderboardCountsOnlySelectedMatches(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $secondVerified = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($secondVerified);

        $subsetCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID));
        self::assertNotNull($subsetCompetition);

        $finishedMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finishedMatch);

        $liveMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_LIVE_ID));
        self::assertNotNull($liveMatch);

        // Evaluated guess on a SELECTED match (MATCH_FINISHED) — must count.
        // S06 fixtures already seed SECOND's guess on it (without evaluation).
        $selectedGuess = $em->find(Guess::class, Uuid::fromString(AppFixtures::SUBSET_GUESS_ID));
        self::assertNotNull($selectedGuess);

        $selectedEvaluation = new GuessEvaluation(id: Uuid::v7(), guess: $selectedGuess, evaluatedAt: $now);
        $selectedEvaluation->addRulePoints(new GuessEvaluationRulePoints(
            id: Uuid::v7(),
            evaluation: $selectedEvaluation,
            ruleIdentifier: 'correct_outcome',
            points: 3,
        ));
        $em->persist($selectedEvaluation);

        // Evaluated guess on a NOT-selected match (MATCH_LIVE) — must be ignored.
        $excludedGuess = new Guess(
            id: Uuid::v7(),
            user: $secondVerified,
            sportMatch: $liveMatch,
            competition: $subsetCompetition,
            homeScore: 5,
            awayScore: 0,
            submittedAt: $now,
        );
        $excludedGuess->popEvents();
        $em->persist($excludedGuess);

        $excludedEvaluation = new GuessEvaluation(id: Uuid::v7(), guess: $excludedGuess, evaluatedAt: $now);
        $excludedEvaluation->addRulePoints(new GuessEvaluationRulePoints(
            id: Uuid::v7(),
            evaluation: $excludedEvaluation,
            ruleIdentifier: 'exact_score',
            points: 5,
        ));
        $em->persist($excludedEvaluation);

        $em->flush();

        $result = $this->queryBus()->handle(new GetCompetitionLeaderboard(
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
        ));

        self::assertCount(1, $result->rows);
        self::assertSame(AppFixtures::SECOND_VERIFIED_USER_NICKNAME, $result->rows[0]->nickname);
        self::assertSame(3, $result->rows[0]->totalPoints, 'Only the selected match counts.');
        self::assertSame(1, $result->rows[0]->evaluatedCount);
        self::assertSame(0, $result->rows[0]->exactCount, 'Exact hit on the unselected match must not count.');
    }

    public function testMemberWithoutGuessesHasZeroPoints(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);

        $publicCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($publicCompetition);

        $membership = new Membership(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $result = $this->queryBus()->handle(new GetCompetitionLeaderboard(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        self::assertCount(2, $result->rows);

        $verifiedRow = null;
        foreach ($result->rows as $row) {
            if (AppFixtures::VERIFIED_USER_NICKNAME === $row->nickname) {
                $verifiedRow = $row;
            }
        }

        self::assertNotNull($verifiedRow);
        self::assertSame(0, $verifiedRow->totalPoints);
        self::assertSame(2, $verifiedRow->rank);
    }
}
