<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SendBulkGroupInvitations\BulkInvitationResult;
use App\Command\SendBulkGroupInvitations\SendBulkGroupInvitationsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

final class SendBulkGroupInvitationsHandlerTest extends IntegrationTestCase
{
    public function testCreatesStubUserForUnknownEmailAndIssuesInvitation(): void
    {
        $envelope = $this->commandBus()->dispatch(new SendBulkGroupInvitationsCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            rawEmails: 'brand-new@example.com',
        ));

        /** @var BulkInvitationResult $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertSame(['brand-new@example.com'], $result->invited);

        $em = $this->entityManager();
        $em->clear();

        $user = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')->setParameter('e', 'brand-new@example.com')
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified);
        self::assertFalse($user->hasPassword);

        $invitations = $em->createQueryBuilder()
            ->select('i')->from(GroupInvitation::class, 'i')
            ->where('i.email = :e')->setParameter('e', 'brand-new@example.com')
            ->getQuery()->getResult();
        self::assertCount(1, $invitations);

        $membership = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :uid')->setParameter('uid', $user->id)
            ->andWhere('m.group = :gid')->setParameter('gid', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->andWhere('m.leftAt IS NULL')
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Membership::class, $membership);
    }

    public function testReusesExistingUserWithoutCreatingDuplicate(): void
    {
        $envelope = $this->commandBus()->dispatch(new SendBulkGroupInvitationsCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            rawEmails: AppFixtures::UNVERIFIED_USER_EMAIL,
        ));

        /** @var BulkInvitationResult $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertSame([AppFixtures::UNVERIFIED_USER_EMAIL], $result->invited);

        $em = $this->entityManager();
        $em->clear();

        $users = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')->setParameter('e', AppFixtures::UNVERIFIED_USER_EMAIL)
            ->getQuery()->getResult();
        self::assertCount(1, $users);
    }

    public function testSkipsExistingMember(): void
    {
        // Verified user is already a member of VERIFIED_GROUP (they own it).
        $envelope = $this->commandBus()->dispatch(new SendBulkGroupInvitationsCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            rawEmails: AppFixtures::VERIFIED_USER_EMAIL,
        ));

        /** @var BulkInvitationResult $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertSame([], $result->invited);
        self::assertSame([AppFixtures::VERIFIED_USER_EMAIL], $result->alreadyMembers);
    }

    public function testSkipsPendingInvitationEmail(): void
    {
        // PUBLIC_GROUP has PENDING_INVITATION_EMAIL as pending.
        $envelope = $this->commandBus()->dispatch(new SendBulkGroupInvitationsCommand(
            inviterId: Uuid::fromString(AppFixtures::ADMIN_ID),
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            rawEmails: AppFixtures::PENDING_INVITATION_EMAIL,
        ));

        /** @var BulkInvitationResult $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertSame([], $result->invited);
        self::assertSame([AppFixtures::PENDING_INVITATION_EMAIL], $result->alreadyPending);
    }

    public function testParsesMixedSeparatorsAndDeduplicates(): void
    {
        $envelope = $this->commandBus()->dispatch(new SendBulkGroupInvitationsCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            rawEmails: "first@example.com\nsecond@example.com, third@example.com; first@example.com",
        ));

        /** @var BulkInvitationResult $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertSame(
            ['first@example.com', 'second@example.com', 'third@example.com'],
            $result->invited,
        );
    }

    public function testReportsInvalidEmailsWithoutAbortingBatch(): void
    {
        $envelope = $this->commandBus()->dispatch(new SendBulkGroupInvitationsCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            rawEmails: "not-an-email\nok@example.com",
        ));

        /** @var BulkInvitationResult $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertSame(['ok@example.com'], $result->invited);
        self::assertCount(1, $result->invalid);
        self::assertSame('not-an-email', $result->invalid[0]['email']);
    }

    public function testNormalisesCaseAndWhitespace(): void
    {
        $envelope = $this->commandBus()->dispatch(new SendBulkGroupInvitationsCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            rawEmails: '  UPPER@Example.COM  ',
        ));

        /** @var BulkInvitationResult $result */
        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertSame(['upper@example.com'], $result->invited);
    }
}
