<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\AcceptGroupInvitation\AcceptGroupInvitationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\GroupInvitationExpired;
use App\Exception\InvalidInvitationToken;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class AcceptGroupInvitationHandlerTest extends IntegrationTestCase
{
    public function testAcceptCreatesMembershipAndMarksAccepted(): void
    {
        $user = $this->createVerifiedUser();

        $this->commandBus()->dispatch(new AcceptGroupInvitationCommand(
            userId: $user->id,
            token: AppFixtures::PENDING_INVITATION_TOKEN,
        ));

        $em = $this->entityManager();
        $em->clear();

        $invitation = $em->find(GroupInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        self::assertNotNull($invitation);
        self::assertTrue($invitation->isAccepted);

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.group = :groupId')
            ->setParameter('userId', $user->id)
            ->setParameter('groupId', Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
    }

    public function testAcceptVerifiesUserWhenInvitationEmailMatches(): void
    {
        $em = $this->entityManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $unverified = new User(
            id: $this->identityProvider()->next(),
            email: AppFixtures::PENDING_INVITATION_EMAIL,
            password: null,
            nickname: 'invitee_unverified',
            createdAt: $now,
        );
        $unverified->changePassword($hasher->hashPassword($unverified, 'password'), $now);
        $unverified->popEvents();
        $em->persist($unverified);
        $em->flush();

        self::assertFalse($unverified->isVerified);

        $this->commandBus()->dispatch(new AcceptGroupInvitationCommand(
            userId: $unverified->id,
            token: AppFixtures::PENDING_INVITATION_TOKEN,
        ));

        $em->clear();

        $reloaded = $em->find(User::class, $unverified->id);
        self::assertNotNull($reloaded);
        self::assertTrue(
            $reloaded->isVerified,
            'Receiving the invitation in the user mailbox proves email ownership.',
        );
    }

    public function testAcceptDoesNotVerifyWhenInvitationEmailMismatches(): void
    {
        $em = $this->entityManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $unverified = new User(
            id: $this->identityProvider()->next(),
            email: 'someone-else@tipovacka.test',
            password: null,
            nickname: 'invitee_other_email',
            createdAt: $now,
        );
        $unverified->changePassword($hasher->hashPassword($unverified, 'password'), $now);
        $unverified->popEvents();
        $em->persist($unverified);
        $em->flush();

        $this->commandBus()->dispatch(new AcceptGroupInvitationCommand(
            userId: $unverified->id,
            token: AppFixtures::PENDING_INVITATION_TOKEN,
        ));

        $em->clear();

        $reloaded = $em->find(User::class, $unverified->id);
        self::assertNotNull($reloaded);
        self::assertFalse(
            $reloaded->isVerified,
            'Verification must require the invitation email and the user email to match.',
        );
    }

    public function testAcceptAlreadyMemberDoesNotDuplicateMembership(): void
    {
        $em = $this->entityManager();
        // Admin is already a member of PUBLIC_GROUP (owner). Make them accept the invitation.
        $this->commandBus()->dispatch(new AcceptGroupInvitationCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            token: AppFixtures::PENDING_INVITATION_TOKEN,
        ));

        $em->clear();

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.group = :groupId')
            ->setParameter('userId', Uuid::fromString(AppFixtures::ADMIN_ID))
            ->setParameter('groupId', Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID))
            ->getQuery()
            ->getResult();

        // One pre-existing ownership membership, no duplicate.
        self::assertCount(1, $memberships);

        $invitation = $em->find(GroupInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        self::assertNotNull($invitation);
        self::assertTrue($invitation->isAccepted);
    }

    public function testInvalidTokenThrows(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new AcceptGroupInvitationCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                token: str_repeat('0', 64),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvalidInvitationToken::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testExpiredInvitationThrows(): void
    {
        $em = $this->entityManager();

        // Expire the pending invitation.
        $invitation = $em->find(GroupInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        self::assertNotNull($invitation);

        $connection = $em->getConnection();
        $connection->executeStatement(
            'UPDATE group_invitations SET expires_at = :past WHERE id = :id',
            [
                'past' => '2024-01-01 00:00:00',
                'id' => AppFixtures::PENDING_INVITATION_ID,
            ]
        );
        $em->clear();

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new AcceptGroupInvitationCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                token: AppFixtures::PENDING_INVITATION_TOKEN,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GroupInvitationExpired::class, $e->getPrevious());

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
            email: 'invitee@tipovacka.test',
            password: null,
            nickname: 'invitee',
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
