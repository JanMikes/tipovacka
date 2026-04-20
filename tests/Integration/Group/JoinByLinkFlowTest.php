<?php

declare(strict_types=1);

namespace App\Tests\Integration\Group;

use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class JoinByLinkFlowTest extends WebTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/skupiny/pozvanka/'.AppFixtures::VERIFIED_GROUP_LINK_TOKEN);

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testInvalidTokenReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/skupiny/pozvanka/'.str_repeat('0', 48));

        self::assertResponseStatusCodeSame(404);
    }

    public function testAuthenticatedUserJoinsViaLink(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: Uuid::v7(),
            email: 'linkflow@tipovacka.test',
            password: null,
            nickname: 'linkflow_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        $client->request('GET', '/skupiny/pozvanka/'.AppFixtures::VERIFIED_GROUP_LINK_TOKEN);
        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);

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
}
