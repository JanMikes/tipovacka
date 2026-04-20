<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CompleteInvitationRegistration\CompleteInvitationRegistrationCommand;
use App\Command\SendBulkGroupInvitations\SendBulkGroupInvitationsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\InvitationAlreadyRegistered;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class CompleteInvitationRegistrationHandlerTest extends IntegrationTestCase
{
    public function testSetsPasswordMarksVerifiedAndJoinsGroup(): void
    {
        $this->commandBus()->dispatch(new SendBulkGroupInvitationsCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            rawEmails: 'fresh@example.com',
        ));

        $token = $this->findInvitationToken('fresh@example.com');

        $this->commandBus()->dispatch(new CompleteInvitationRegistrationCommand(
            token: $token,
            plainPassword: 'Str0ngPassword!',
        ));

        $em = $this->entityManager();
        $em->clear();

        $user = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')->setParameter('e', 'fresh@example.com')
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->hasPassword);
        self::assertTrue($user->isVerified);

        $membership = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :u')
            ->andWhere('m.group = :g')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('u', $user->id)
            ->setParameter('g', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Membership::class, $membership);

        /** @var GroupInvitation $invitation */
        $invitation = $em->createQueryBuilder()
            ->select('i')->from(GroupInvitation::class, 'i')
            ->where('i.token = :t')->setParameter('t', $token)
            ->getQuery()->getOneOrNullResult();
        self::assertTrue($invitation->isAccepted);
    }

    public function testRejectsWhenUserAlreadyHasPassword(): void
    {
        $this->commandBus()->dispatch(new SendBulkGroupInvitationsCommand(
            inviterId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            rawEmails: AppFixtures::UNVERIFIED_USER_EMAIL, // already has password from fixtures
        ));

        $token = $this->findInvitationToken(AppFixtures::UNVERIFIED_USER_EMAIL);

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new CompleteInvitationRegistrationCommand(
                token: $token,
                plainPassword: 'AnyStr0ng!',
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvitationAlreadyRegistered::class, $e->getPrevious());

            throw $e;
        }
    }

    private function findInvitationToken(string $email): string
    {
        $invitation = $this->entityManager()
            ->createQueryBuilder()
            ->select('i')->from(GroupInvitation::class, 'i')
            ->where('i.email = :e')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.revokedAt IS NULL')
            ->setParameter('e', strtolower($email))
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        \assert($invitation instanceof GroupInvitation);

        return $invitation->token;
    }
}
