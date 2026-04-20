<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class NavigationTest extends WebTestCase
{
    public function testAnonymousSeesLoginAndRegisterLinks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/prihlaseni');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/prihlaseni"]');
        self::assertSelectorExists('a[href="/registrace"]');
    }

    public function testAuthenticatedSeesProfileAndLogoutLinks(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/portal/profil"]');
        self::assertSelectorExists('a[href="/odhlaseni"]');
    }

    public function testAdminSeesAdminLink(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href^="/admin/turnaje"]');
    }
}
