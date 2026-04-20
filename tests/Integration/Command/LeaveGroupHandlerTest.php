<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\JoinGroupByPin\JoinGroupByPinCommand;
use App\Command\LeaveGroup\LeaveGroupCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\CannotLeaveAsOwner;
use App\Exception\NotAMember;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class LeaveGroupHandlerTest extends IntegrationTestCase
{
    public function testMemberLeavesGroup(): void
    {
        $user = $this->createVerifiedUser();

        // First join.
        $this->commandBus()->dispatch(new JoinGroupByPinCommand(
            userId: $user->id,
            pin: AppFixtures::VERIFIED_GROUP_PIN,
        ));

        // Then leave.
        $this->commandBus()->dispatch(new LeaveGroupCommand(
            userId: $user->id,
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.group = :groupId')
            ->setParameter('userId', $user->id)
            ->setParameter('groupId', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
        self::assertNotNull($memberships[0]->leftAt);
    }

    public function testOwnerCannotLeave(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new LeaveGroupCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CannotLeaveAsOwner::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testNonMemberCannotLeave(): void
    {
        $user = $this->createVerifiedUser();

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new LeaveGroupCommand(
                userId: $user->id,
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(NotAMember::class, $e->getPrevious());

            throw $e;
        }
    }

    private function createVerifiedUser(): User
    {
        $em = $this->entityManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: $this->identityProvider()->next(),
            email: 'leaver@tipovacka.test',
            password: null,
            nickname: 'leaver',
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
