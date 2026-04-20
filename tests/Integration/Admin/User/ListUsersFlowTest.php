<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\User;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ListUsersFlowTest extends WebTestCase
{
    public function testNonAdminCannotAccess(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/admin/uzivatele');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanListUsers(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/uzivatele');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString(AppFixtures::VERIFIED_USER_EMAIL, $body);
        self::assertStringContainsString(AppFixtures::UNVERIFIED_USER_EMAIL, $body);
    }

    public function testSearchFilter(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/uzivatele', [
            'search' => AppFixtures::UNVERIFIED_USER_NICKNAME,
            'verified' => 'all',
            'active' => 'all',
        ]);
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString(AppFixtures::UNVERIFIED_USER_EMAIL, $body);
        self::assertStringNotContainsString(AppFixtures::VERIFIED_USER_EMAIL, $body);
    }
}
