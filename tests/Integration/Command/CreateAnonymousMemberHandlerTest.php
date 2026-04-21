<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateAnonymousMember\CreateAnonymousMemberCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\NicknameAlreadyTaken;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class CreateAnonymousMemberHandlerTest extends IntegrationTestCase
{
    public function testOwnerCreatesAnonymousMember(): void
    {
        $envelope = $this->commandBus()->dispatch(new CreateAnonymousMemberCommand(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            actorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            firstName: 'Pepa',
            lastName: 'Tipa',
            nickname: null,
        ));

        $stamp = $envelope->last(HandledStamp::class);
        self::assertNotNull($stamp);

        /** @var User $created */
        $created = $stamp->getResult();

        $em = $this->entityManager();
        $em->clear();

        $user = $em->find(User::class, $created->id);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isAnonymous);
        self::assertNull($user->email);
        self::assertNull($user->nickname);
        self::assertFalse($user->hasPassword);
        self::assertSame('Pepa', $user->firstName);
        self::assertSame('Tipa', $user->lastName);
        // Display falls back to full name when no nickname was typed.
        self::assertSame('Pepa Tipa', $user->displayName);

        $membership = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :uid')->setParameter('uid', $user->id)
            ->andWhere('m.group = :gid')->setParameter('gid', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->andWhere('m.leftAt IS NULL')
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Membership::class, $membership);
    }

    public function testNonOwnerNonAdminIsRejected(): void
    {
        // UNVERIFIED_USER is neither admin nor the owner of VERIFIED_GROUP.
        try {
            $this->commandBus()->dispatch(new CreateAnonymousMemberCommand(
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                actorId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
                firstName: 'Intruder',
                lastName: 'X',
                nickname: null,
            ));
            self::fail('Expected AccessDeniedException');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(AccessDeniedException::class, $e->getPrevious());
        }
    }

    public function testNicknameCollisionIsRejected(): void
    {
        try {
            $this->commandBus()->dispatch(new CreateAnonymousMemberCommand(
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                actorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                firstName: 'Pepa',
                lastName: 'Tipa',
                nickname: AppFixtures::ADMIN_NICKNAME,
            ));
            self::fail('Expected NicknameAlreadyTaken');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(NicknameAlreadyTaken::class, $e->getPrevious());
        }
    }

    public function testProvidedNicknameTakesPrecedenceInDisplayName(): void
    {
        $envelope = $this->commandBus()->dispatch(new CreateAnonymousMemberCommand(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            actorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            firstName: 'Karel',
            lastName: 'Zlý',
            nickname: 'karlos',
        ));

        $stamp = $envelope->last(HandledStamp::class);
        self::assertNotNull($stamp);
        /** @var User $created */
        $created = $stamp->getResult();

        $em = $this->entityManager();
        $em->clear();

        $user = $em->find(User::class, $created->id);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('karlos', $user->nickname);
        self::assertSame('karlos', $user->displayName);
    }
}
