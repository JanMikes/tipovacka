<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\ResolveLeaderboardTies\ResolveLeaderboardTiesCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\LeaderboardTieResolution;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Exception\LeaderboardTieResolutionInvalid;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class ResolveLeaderboardTiesHandlerTest extends IntegrationTestCase
{
    public function testResolvesTieForTwoMembersWithEqualPoints(): void
    {
        $this->seedSecondMemberWithMatchingPoints();

        $this->commandBus()->dispatch(new ResolveLeaderboardTiesCommand(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            resolverId: Uuid::fromString(AppFixtures::ADMIN_ID),
            orderedUserIds: [
                Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                Uuid::fromString(AppFixtures::ADMIN_ID),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<LeaderboardTieResolution> $rows */
        $rows = $em->createQueryBuilder()
            ->select('r', 'u')
            ->from(LeaderboardTieResolution::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.group = :groupId')
            ->setParameter('groupId', Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID))
            ->orderBy('r.rank', 'ASC')
            ->getQuery()
            ->getResult();

        self::assertCount(2, $rows);
        self::assertSame(1, $rows[0]->rank);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $rows[0]->user->nickname);
        self::assertSame(2, $rows[1]->rank);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $rows[1]->user->nickname);
    }

    public function testRejectsUsersThatAreNotTied(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new ResolveLeaderboardTiesCommand(
                groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
                resolverId: Uuid::fromString(AppFixtures::ADMIN_ID),
                orderedUserIds: [
                    Uuid::fromString(AppFixtures::ADMIN_ID),
                    Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                ],
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            self::assertInstanceOf(LeaderboardTieResolutionInvalid::class, $previous);

            throw $e;
        }
    }

    public function testReResolvingReplacesPreviousOrder(): void
    {
        $this->seedSecondMemberWithMatchingPoints();

        $this->commandBus()->dispatch(new ResolveLeaderboardTiesCommand(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            resolverId: Uuid::fromString(AppFixtures::ADMIN_ID),
            orderedUserIds: [
                Uuid::fromString(AppFixtures::ADMIN_ID),
                Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            ],
        ));

        $this->commandBus()->dispatch(new ResolveLeaderboardTiesCommand(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            resolverId: Uuid::fromString(AppFixtures::ADMIN_ID),
            orderedUserIds: [
                Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                Uuid::fromString(AppFixtures::ADMIN_ID),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var list<LeaderboardTieResolution> $rows */
        $rows = $em->createQueryBuilder()
            ->select('r', 'u')
            ->from(LeaderboardTieResolution::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.group = :groupId')
            ->setParameter('groupId', Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID))
            ->orderBy('r.rank', 'ASC')
            ->getQuery()
            ->getResult();

        self::assertCount(2, $rows);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $rows[0]->user->nickname);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $rows[1]->user->nickname);
    }

    private function seedSecondMemberWithMatchingPoints(): void
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
            homeScore: 4,
            awayScore: 2,
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
    }
}
