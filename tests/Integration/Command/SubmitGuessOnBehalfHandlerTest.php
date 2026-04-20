<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SubmitGuessOnBehalf\SubmitGuessOnBehalfCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Entity\Membership;
use App\Exception\GuessAlreadyExists;
use App\Exception\NotAMember;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class SubmitGuessOnBehalfHandlerTest extends IntegrationTestCase
{
    public function testOwnerCanSubmitGuessForActiveMember(): void
    {
        // Add the unverified user as a member of the verified group (private tournament, verified user owns it).
        $this->addActiveMembership(
            groupId: AppFixtures::VERIFIED_GROUP_ID,
            userId: AppFixtures::UNVERIFIED_USER_ID,
        );

        $this->commandBus()->dispatch(new SubmitGuessOnBehalfCommand(
            actingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            targetUserId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->setParameter('u', Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(Guess::class, $guess);
        self::assertSame(2, $guess->homeScore);
        self::assertSame(1, $guess->awayScore);
        self::assertNotNull($guess->submittedBy);
        self::assertTrue($guess->submittedBy->id->equals(Uuid::fromString(AppFixtures::VERIFIED_USER_ID)));
    }

    public function testNonOwnerNonAdminCannotSubmitOnBehalf(): void
    {
        // Second verified user joins the public group as a regular member.
        $this->addActiveMembership(
            groupId: AppFixtures::PUBLIC_GROUP_ID,
            userId: AppFixtures::SECOND_VERIFIED_USER_ID,
        );

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessOnBehalfCommand(
                actingUserId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                targetUserId: Uuid::fromString(AppFixtures::ADMIN_ID),
                groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                homeScore: 1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(AccessDeniedException::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testFailsWhenTargetIsNotAMember(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessOnBehalfCommand(
                actingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                targetUserId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                homeScore: 1,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(NotAMember::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testFailsOnDuplicate(): void
    {
        $this->addActiveMembership(
            groupId: AppFixtures::VERIFIED_GROUP_ID,
            userId: AppFixtures::UNVERIFIED_USER_ID,
        );

        $this->commandBus()->dispatch(new SubmitGuessOnBehalfCommand(
            actingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            targetUserId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new SubmitGuessOnBehalfCommand(
                actingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                targetUserId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
                homeScore: 3,
                awayScore: 0,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GuessAlreadyExists::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testAdminCanSubmitOnBehalfOfAnyMember(): void
    {
        $this->addActiveMembership(
            groupId: AppFixtures::VERIFIED_GROUP_ID,
            userId: AppFixtures::UNVERIFIED_USER_ID,
        );

        // Admin is not the owner of the verified (private) group; verified user is.
        $this->commandBus()->dispatch(new SubmitGuessOnBehalfCommand(
            actingUserId: Uuid::fromString(AppFixtures::ADMIN_ID),
            targetUserId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 4,
            awayScore: 2,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->setParameter('u', Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(Guess::class, $guess);
        self::assertSame(4, $guess->homeScore);
    }

    private function addActiveMembership(string $groupId, string $userId): void
    {
        $em = $this->entityManager();

        $group = $em->find(\App\Entity\Group::class, Uuid::fromString($groupId));
        $user = $em->find(\App\Entity\User::class, Uuid::fromString($userId));

        \assert(null !== $group && null !== $user);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $group,
            user: $user,
            joinedAt: \DateTimeImmutable::createFromInterface($this->clock()->now()),
        );
        $em->persist($membership);
        $em->flush();
    }
}
