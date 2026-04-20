<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SubmitGuessOnBehalf\SubmitGuessOnBehalfCommand;
use App\Command\UpdateGuessOnBehalf\UpdateGuessOnBehalfCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Guess;
use App\Entity\Membership;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class UpdateGuessOnBehalfHandlerTest extends IntegrationTestCase
{
    public function testOwnerCanUpdateMemberGuess(): void
    {
        $this->addActiveMembership(
            groupId: AppFixtures::VERIFIED_GROUP_ID,
            userId: AppFixtures::UNVERIFIED_USER_ID,
        );

        $envelope = $this->commandBus()->dispatch(new SubmitGuessOnBehalfCommand(
            actingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            targetUserId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
        ));

        /** @var Guess $guess */
        $guess = $envelope->last(HandledStamp::class)?->getResult();

        $this->commandBus()->dispatch(new UpdateGuessOnBehalfCommand(
            actingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            guessId: $guess->id,
            homeScore: 3,
            awayScore: 0,
        ));

        $em = $this->entityManager();
        $em->clear();

        /** @var Guess $refreshed */
        $refreshed = $em->find(Guess::class, $guess->id);
        self::assertSame(3, $refreshed->homeScore);
        self::assertSame(0, $refreshed->awayScore);
    }

    public function testNonOwnerCannotUpdateMemberGuess(): void
    {
        $this->addActiveMembership(
            groupId: AppFixtures::VERIFIED_GROUP_ID,
            userId: AppFixtures::UNVERIFIED_USER_ID,
        );
        $this->addActiveMembership(
            groupId: AppFixtures::VERIFIED_GROUP_ID,
            userId: AppFixtures::SECOND_VERIFIED_USER_ID,
        );

        $envelope = $this->commandBus()->dispatch(new SubmitGuessOnBehalfCommand(
            actingUserId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            targetUserId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
        ));
        /** @var Guess $guess */
        $guess = $envelope->last(HandledStamp::class)?->getResult();

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new UpdateGuessOnBehalfCommand(
                actingUserId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                guessId: $guess->id,
                homeScore: 5,
                awayScore: 5,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(AccessDeniedException::class, $e->getPrevious());

            throw $e;
        }
    }

    private function addActiveMembership(string $groupId, string $userId): void
    {
        $em = $this->entityManager();

        $group = $em->find(\App\Entity\Group::class, Uuid::fromString($groupId));
        $user = $em->find(\App\Entity\User::class, Uuid::fromString($userId));

        \assert(null !== $group && null !== $user);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $group,
            user: $user,
            joinedAt: \DateTimeImmutable::createFromInterface($this->clock()->now()),
        );
        $em->persist($membership);
        $em->flush();
    }
}
