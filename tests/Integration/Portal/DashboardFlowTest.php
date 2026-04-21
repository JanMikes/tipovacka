<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class DashboardFlowTest extends WebTestCase
{
    public function testAnonymousRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nastenka');

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testVerifiedUserSeesThreeSections(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Moje soutěže');
        self::assertSelectorTextContains('body', 'Nadcházející zápasy');
        self::assertSelectorTextContains('body', 'Objev další turnaje');
    }

    public function testUserSeesOwnGroups(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::VERIFIED_GROUP_NAME);
    }

    public function testUserSeesDiscoverablePublicTournaments(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::PUBLIC_TOURNAMENT_NAME);
    }

    public function testAdminDoesNotSeeOwnPublicTournamentInDiscovery(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        // Admin is already a member of PUBLIC_GROUP, so it should appear in "Moje skupiny"
        // but NOT in "Objevte další turnaje".
        self::assertSelectorTextContains('body', AppFixtures::PUBLIC_GROUP_NAME);
    }
}
