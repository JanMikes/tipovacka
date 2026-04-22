<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\LeaderboardTieResolution;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Query\GetGroupLeaderboard\GetGroupLeaderboard;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetGroupLeaderboardQueryTest extends IntegrationTestCase
{
    public function testAdminHasThreePointsAsSoleEvaluatedMember(): void
    {
        $result = $this->queryBus()->handle(new GetGroupLeaderboard(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
        ));

        self::assertCount(1, $result->rows);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $result->rows[0]->nickname);
        self::assertNull($result->rows[0]->fullName, 'Admin has no fullName set, so subtitle stays null.');
        self::assertSame(3, $result->rows[0]->totalPoints);
        self::assertSame(1, $result->rows[0]->rank);
        self::assertFalse($result->rows[0]->isTieResolvedOverride);
        self::assertFalse($result->tournamentFinished);
    }

    public function testFullNameSubtitleIsSetWhenUserHasBothNicknameAndFullName(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $verified->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: $now);

        $publicGroup = $em->find(Group::class, Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID));
        self::assertNotNull($publicGroup);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $publicGroup,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $result = $this->queryBus()->handle(new GetGroupLeaderboard(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
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

        $publicGroup = $em->find(Group::class, Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID));
        self::assertNotNull($publicGroup);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $publicGroup,
            user: $anonymous,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $result = $this->queryBus()->handle(new GetGroupLeaderboard(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
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

        $publicGroup = $em->find(Group::class, Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID));
        self::assertNotNull($publicGroup);

        $finishedMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finishedMatch);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $publicGroup,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $verified,
            sportMatch: $finishedMatch,
            group: $publicGroup,
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

        $result = $this->queryBus()->handle(new GetGroupLeaderboard(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
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

        $publicGroup = $em->find(Group::class, Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID));
        self::assertNotNull($publicGroup);

        $finishedMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_FINISHED_ID));
        self::assertNotNull($finishedMatch);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $publicGroup,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $verified,
            sportMatch: $finishedMatch,
            group: $publicGroup,
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
            group: $publicGroup,
            user: $admin,
            rank: 1,
            resolvedAt: $now,
            resolvedBy: $admin,
        ));
        $em->persist(new LeaderboardTieResolution(
            id: Uuid::v7(),
            group: $publicGroup,
            user: $verified,
            rank: 2,
            resolvedAt: $now,
            resolvedBy: $admin,
        ));

        $em->flush();

        $result = $this->queryBus()->handle(new GetGroupLeaderboard(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
        ));

        self::assertCount(2, $result->rows);
        self::assertSame(1, $result->rows[0]->rank);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $result->rows[0]->nickname);
        self::assertTrue($result->rows[0]->isTieResolvedOverride);
        self::assertSame(2, $result->rows[1]->rank);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $result->rows[1]->nickname);
        self::assertTrue($result->rows[1]->isTieResolvedOverride);
    }

    public function testMemberWithoutGuessesHasZeroPoints(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);

        $publicGroup = $em->find(Group::class, Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID));
        self::assertNotNull($publicGroup);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $publicGroup,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $result = $this->queryBus()->handle(new GetGroupLeaderboard(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
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
