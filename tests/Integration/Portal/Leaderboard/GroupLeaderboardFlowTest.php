<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Leaderboard;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class GroupLeaderboardFlowTest extends WebTestCase
{
    public function testMemberCanViewLeaderboard(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zebricek');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Žebříček');
    }

    public function testNonMemberReceivesForbidden(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $stranger = new User(
            id: Uuid::v7(),
            email: 'leaderboard-stranger@tipovacka.test',
            password: null,
            nickname: 'lb_stranger_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        $client->loginUser($stranger);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zebricek');
        self::assertResponseStatusCodeSame(403);
    }

    public function testMemberBreakdownPageRenders(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request(
            'GET',
            '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zebricek/clen/'.AppFixtures::ADMIN_ID,
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', AppFixtures::ADMIN_NICKNAME);
    }

    public function testResolveTiesBlockedWhenTournamentActive(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zebricek/shoda');
        self::assertResponseStatusCodeSame(403);
    }
}
