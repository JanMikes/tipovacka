<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\PromoteAnonymousMember\PromoteAnonymousMemberCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\User;
use App\Exception\UserAlreadyExists;
use App\Exception\UserAlreadyPromoted;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class PromoteAnonymousMemberHandlerTest extends IntegrationTestCase
{
    public function testOwnerPromotesAnonymousMember(): void
    {
        $envelope = $this->commandBus()->dispatch(new PromoteAnonymousMemberCommand(
            userId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            actorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: '  FrantA@example.COM  ',
        ));

        $stamp = $envelope->last(HandledStamp::class);
        self::assertNotNull($stamp);
        $invitation = $stamp->getResult();
        self::assertInstanceOf(GroupInvitation::class, $invitation);
        self::assertSame('franta@example.com', $invitation->email);

        $em = $this->entityManager();
        $em->clear();

        $user = $em->find(User::class, Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID));
        self::assertInstanceOf(User::class, $user);
        self::assertSame('franta@example.com', $user->email);
        self::assertFalse($user->isAnonymous);
        self::assertFalse($user->hasPassword);
    }

    public function testRejectsWhenUserAlreadyHasEmail(): void
    {
        // VERIFIED_USER is not anonymous.
        try {
            $this->commandBus()->dispatch(new PromoteAnonymousMemberCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                actorId: Uuid::fromString(AppFixtures::ADMIN_ID),
                email: 'new@example.com',
            ));
            self::fail('Expected UserAlreadyPromoted');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(UserAlreadyPromoted::class, $e->getPrevious());
        }
    }

    public function testRejectsWhenEmailAlreadyInUse(): void
    {
        try {
            $this->commandBus()->dispatch(new PromoteAnonymousMemberCommand(
                userId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                actorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                email: AppFixtures::ADMIN_EMAIL,
            ));
            self::fail('Expected UserAlreadyExists');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(UserAlreadyExists::class, $e->getPrevious());
        }
    }

    public function testNonOwnerNonAdminIsRejected(): void
    {
        try {
            $this->commandBus()->dispatch(new PromoteAnonymousMemberCommand(
                userId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
                groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
                actorId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
                email: 'new@example.com',
            ));
            self::fail('Expected AccessDeniedException');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(AccessDeniedException::class, $e->getPrevious());
        }
    }
}
