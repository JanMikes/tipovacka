<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SendGroupInvitation\SendGroupInvitationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
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
}
