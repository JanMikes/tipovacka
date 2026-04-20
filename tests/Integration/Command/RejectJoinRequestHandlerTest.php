<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RejectJoinRequest\RejectJoinRequestCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GroupJoinRequest;
use App\Enum\JoinRequestDecision;
use App\Exception\GroupJoinRequestAlreadyDecided;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class RejectJoinRequestHandlerTest extends IntegrationTestCase
{
    public function testRejectMarksDecided(): void
    {
        $this->commandBus()->dispatch(new RejectJoinRequestCommand(
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            requestId: Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $request = $em->find(GroupJoinRequest::class, Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID));
        self::assertNotNull($request);
        self::assertTrue($request->isRejected);
        self::assertSame(JoinRequestDecision::Rejected, $request->decision);
    }

    public function testRejectAlreadyDecidedThrows(): void
    {
        $this->commandBus()->dispatch(new RejectJoinRequestCommand(
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            requestId: Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID),
        ));

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new RejectJoinRequestCommand(
                ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
                requestId: Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GroupJoinRequestAlreadyDecided::class, $e->getPrevious());

            throw $e;
        }
    }
}
