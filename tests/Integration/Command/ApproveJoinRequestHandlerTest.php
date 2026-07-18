<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\ApproveJoinRequest\ApproveJoinRequestCommand;
use App\Command\RejectJoinRequest\RejectJoinRequestCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionJoinRequest;
use App\Entity\Membership;
use App\Exception\CompetitionJoinRequestAlreadyDecided;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class ApproveJoinRequestHandlerTest extends IntegrationTestCase
{
    public function testApproveCreatesMembership(): void
    {
        // Admin owns PUBLIC_COMPETITION; fixture has VERIFIED_USER pending.
        $this->commandBus()->dispatch(new ApproveJoinRequestCommand(
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            requestId: Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $request = $em->find(CompetitionJoinRequest::class, Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID));
        self::assertNotNull($request);
        self::assertTrue($request->isApproved);

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.competition = :competitionId')
            ->setParameter('userId', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
    }

    public function testApproveAlreadyDecidedThrows(): void
    {
        // First, reject the request.
        $this->commandBus()->dispatch(new RejectJoinRequestCommand(
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            requestId: Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID),
        ));

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new ApproveJoinRequestCommand(
                ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
                requestId: Uuid::fromString(AppFixtures::PENDING_JOIN_REQUEST_ID),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionJoinRequestAlreadyDecided::class, $e->getPrevious());

            throw $e;
        }
    }
}
