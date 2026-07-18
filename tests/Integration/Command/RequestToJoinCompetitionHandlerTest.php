<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RequestToJoinCompetition\RequestToJoinCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionJoinRequest;
use App\Entity\User;
use App\Exception\DuplicatePendingJoinRequest;
use App\Exception\JoinRequestNotAllowed;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class RequestToJoinCompetitionHandlerTest extends IntegrationTestCase
{
    public function testCreatesJoinRequestForPublicCompetition(): void
    {
        $user = $this->createVerifiedUser('requester@tipovacka.test', 'requester1');

        $this->commandBus()->dispatch(new RequestToJoinCompetitionCommand(
            userId: $user->id,
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $requests = $em->createQueryBuilder()
            ->select('r')
            ->from(CompetitionJoinRequest::class, 'r')
            ->where('r.user = :userId')
            ->andWhere('r.competition = :competitionId')
            ->setParameter('userId', $user->id)
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $requests);
    }

    public function testPrivateMatchSourceRejected(): void
    {
        $user = $this->createVerifiedUser('requester2@tipovacka.test', 'requester2');

        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new RequestToJoinCompetitionCommand(
                userId: $user->id,
                competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(JoinRequestNotAllowed::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testDuplicatePendingRejected(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            // Fixture has VERIFIED_USER with a pending request for PUBLIC_COMPETITION.
            $this->commandBus()->dispatch(new RequestToJoinCompetitionCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(DuplicatePendingJoinRequest::class, $e->getPrevious());

            throw $e;
        }
    }

    private function createVerifiedUser(string $email, string $nickname): User
    {
        $em = $this->entityManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: $this->identityProvider()->next(),
            email: $email,
            password: null,
            nickname: $nickname,
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
