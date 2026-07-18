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
        self::assertSelectorTextContains('body', 'Objev další zdroje zápasů');
    }

    public function testUserSeesOwnCompetitions(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::VERIFIED_COMPETITION_NAME);
    }

    public function testUserSeesDiscoverablePublicMatchSources(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::PUBLIC_SOURCE_NAME);
    }

    public function testAdminDoesNotSeeOwnPublicMatchSourceInDiscovery(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        // Admin is already a member of PUBLIC_COMPETITION, so it should appear in "Moje soutěže"
        // but NOT in "Objevte další turnaje".
        self::assertSelectorTextContains('body', AppFixtures::PUBLIC_COMPETITION_NAME);
    }

    public function testUserSeesOwnedMatchSourceInMojeTurnaje(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        // The verified user owns PRIVATE_SOURCE — it must be reachable from
        // the dashboard regardless of competition membership.
        self::assertSelectorTextContains('body', 'Moje zdroje zápasů');
        self::assertSelectorTextContains('body', AppFixtures::PRIVATE_SOURCE_NAME);
    }
}
