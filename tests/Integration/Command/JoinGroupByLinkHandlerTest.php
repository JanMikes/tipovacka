<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\JoinGroupByLink\JoinGroupByLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\InvalidShareableLink;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class JoinGroupByLinkHandlerTest extends IntegrationTestCase
{
    public function testJoinsGroupWithValidToken(): void
    {
        $user = $this->createVerifiedUser();

        $this->commandBus()->dispatch(new JoinGroupByLinkCommand(
            userId: $user->id,
            token: AppFixtures::VERIFIED_GROUP_LINK_TOKEN,
        ));

        $em = $this->entityManager();
        $em->clear();

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.group = :groupId')
            ->setParameter('userId', $user->id)
            ->setParameter('groupId', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
    }

    public function testInvalidTokenThrows(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new JoinGroupByLinkCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                token: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvalidShareableLink::class, $e->getPrevious());

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
            email: 'linkjoiner@tipovacka.test',
            password: null,
            nickname: 'linkjoiner',
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
