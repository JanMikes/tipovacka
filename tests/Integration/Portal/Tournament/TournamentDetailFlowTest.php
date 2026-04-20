<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Tournament;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class TournamentDetailFlowTest extends WebTestCase
{
    public function testOwnerCanViewPrivateTournament(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', AppFixtures::PRIVATE_TOURNAMENT_NAME);
    }

    public function testAdminCanViewPrivateTournament(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID);
        self::assertResponseIsSuccessful();
    }

    public function testNonOwnerReceivesForbiddenOnPrivate(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $nonOwner = new User(
            id: Uuid::v7(),
            email: 'non-owner@tipovacka.test',
            password: null,
            nickname: 'non_owner',
            createdAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $nonOwner->changePassword(
            $hasher->hashPassword($nonOwner, 'password'),
            new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $nonOwner->markAsVerified(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $nonOwner->popEvents();
        $em->persist($nonOwner);
        $em->flush();

        $client->loginUser($nonOwner);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnyoneCanViewPublicTournament(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PUBLIC_TOURNAMENT_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', AppFixtures::PUBLIC_TOURNAMENT_NAME);
    }
}
