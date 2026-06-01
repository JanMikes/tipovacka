<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Leaderboard;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class LeaderboardResolverFlowTest extends WebTestCase
{
    public function testRedirectsMemberToTheirPrimarySoutezLeaderboard(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/zebricek');

        self::assertResponseStatusCodeSame(302);
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertMatchesRegularExpression('#/portal/skupiny/[0-9a-f-]+/zebricek$#', $location);
    }

    public function testRedirectsUserWithNoSoutezToDashboard(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $loner = new User(
            id: Uuid::v7(),
            email: 'leaderboard-loner@tipovacka.test',
            password: null,
            nickname: 'lb_loner_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $loner->changePassword($hasher->hashPassword($loner, 'password'), $now);
        $loner->markAsVerified($now);
        $loner->popEvents();
        $em->persist($loner);
        $em->flush();

        $client->loginUser($loner);

        $client->request('GET', '/portal/zebricek');

        self::assertResponseRedirects('/nastenka');
    }

    public function testAnonymousIsSentToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/portal/zebricek');

        self::assertResponseStatusCodeSame(302);
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertStringContainsString('/prihlaseni', $location);
    }
}
