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

final class AcceptInvitationFlowTest extends WebTestCase
{
    public function testAnonymousStoresIntentAndRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN);

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testInvalidTokenReturns404Landing(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pozvanka/'.str_repeat('0', 64));

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('body', 'Pozvánka nenalezena');
    }

    public function testAuthenticatedVerifiedUserIsAdded(): void
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
            email: 'invflow@tipovacka.test',
            password: null,
            nickname: 'invflow_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        $client->request('GET', '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN);
        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID);

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.group = :groupId')
            ->setParameter('userId', $user->id)
            ->setParameter('groupId', Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
    }

    public function testExpiredInvitationShowsExpiredLanding(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        $em->getConnection()->executeStatement(
            'UPDATE group_invitations SET expires_at = :past WHERE id = :id',
            [
                'past' => '2024-01-01 00:00:00',
                'id' => AppFixtures::PENDING_INVITATION_ID,
            ]
        );

        $client->request('GET', '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Pozvánka vypršela');
    }

    public function testAlreadyAcceptedShowsAcceptedLanding(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $em->getConnection()->executeStatement(
            'UPDATE group_invitations SET accepted_at = :now WHERE id = :id',
            [
                'now' => $now->format('Y-m-d H:i:s'),
                'id' => AppFixtures::PENDING_INVITATION_ID,
            ]
        );
        $em->clear();

        $client->request('GET', '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'již byla přijata');
    }
}
