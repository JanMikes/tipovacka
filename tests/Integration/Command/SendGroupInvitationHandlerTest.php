<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SendGroupInvitation\SendGroupInvitationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

final class SendGroupInvitationHandlerTest extends IntegrationTestCase
{
    public function testPersistsInvitationAndNormalisesEmail(): void
    {
        $envelope = $this->commandBus()->dispatch(new SendGroupInvitationCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            email: '  GUEST@Example.COM  ',
        ));

        $stamp = $envelope->last(HandledStamp::class);
        self::assertNotNull($stamp);

        /** @var GroupInvitation $invitation */
        $invitation = $stamp->getResult();
        self::assertSame('guest@example.com', $invitation->email);
        self::assertSame(64, strlen($invitation->token));
        self::assertFalse($invitation->isAccepted);
        self::assertFalse($invitation->isRevoked);

        $em = $this->entityManager();
        $em->clear();

        $persisted = $em->find(GroupInvitation::class, $invitation->id);
        self::assertNotNull($persisted);
        self::assertSame('guest@example.com', $persisted->email);
    }

    public function testProvisionsStubUserAndActiveMembershipForUnknownEmail(): void
    {
        $this->commandBus()->dispatch(new SendGroupInvitationCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            email: 'fresh@example.com',
        ));

        $em = $this->entityManager();
        $em->clear();

        $user = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')->setParameter('e', 'fresh@example.com')
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified);
        self::assertFalse($user->hasPassword);

        $membership = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :uid')->setParameter('uid', $user->id)
            ->andWhere('m.group = :gid')->setParameter('gid', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->andWhere('m.leftAt IS NULL')
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Membership::class, $membership);
    }

    public function testDoesNotDuplicateMembershipWhenInvitingExistingMember(): void
    {
        $em = $this->entityManager();

        $before = (int) $em->createQueryBuilder()
            ->select('COUNT(m.id)')->from(Membership::class, 'm')
            ->where('m.group = :gid')->setParameter('gid', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->andWhere('m.leftAt IS NULL')
            ->getQuery()->getSingleScalarResult();

        // Owner is already an active member of their own group — reinviting them
        // must not add a second membership row.
        $this->commandBus()->dispatch(new SendGroupInvitationCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
        ));

        $em->clear();

        $after = (int) $em->createQueryBuilder()
            ->select('COUNT(m.id)')->from(Membership::class, 'm')
            ->where('m.group = :gid')->setParameter('gid', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->andWhere('m.leftAt IS NULL')
            ->getQuery()->getSingleScalarResult();

        self::assertSame($before, $after);
    }
}
