<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RevokeCompetitionInvitation\RevokeCompetitionInvitationCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionInvitation;
use App\Exception\NotAMember;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class RevokeCompetitionInvitationHandlerTest extends IntegrationTestCase
{
    public function testOwnerCanRevoke(): void
    {
        // Admin is owner of PUBLIC_COMPETITION, pending invitation is attached to it.
        $this->commandBus()->dispatch(new RevokeCompetitionInvitationCommand(
            revokerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            invitationId: Uuid::fromString(AppFixtures::PENDING_INVITATION_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $invitation = $em->find(CompetitionInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        self::assertNotNull($invitation);
        self::assertTrue($invitation->isRevoked);
    }

    public function testInviterCanRevoke(): void
    {
        // Admin is the inviter in the fixture.
        $this->commandBus()->dispatch(new RevokeCompetitionInvitationCommand(
            revokerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            invitationId: Uuid::fromString(AppFixtures::PENDING_INVITATION_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $invitation = $em->find(CompetitionInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        self::assertNotNull($invitation);
        self::assertTrue($invitation->isRevoked);
    }

    public function testUnrelatedUserCannotRevoke(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new RevokeCompetitionInvitationCommand(
                revokerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                invitationId: Uuid::fromString(AppFixtures::PENDING_INVITATION_ID),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(NotAMember::class, $e->getPrevious());

            throw $e;
        }
    }
}
