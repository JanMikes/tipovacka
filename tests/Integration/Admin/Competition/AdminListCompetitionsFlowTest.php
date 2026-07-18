<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AdminListCompetitionsFlowTest extends WebTestCase
{
    public function testAdminSeesAllCompetitions(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/souteze');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString(AppFixtures::VERIFIED_COMPETITION_NAME, $body);
        self::assertStringContainsString(AppFixtures::PUBLIC_COMPETITION_NAME, $body);
    }

    public function testFilterByMatchSource(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/souteze?match_source='.AppFixtures::PRIVATE_SOURCE_ID);
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString(AppFixtures::VERIFIED_COMPETITION_NAME, $body);
        self::assertStringNotContainsString(AppFixtures::PUBLIC_COMPETITION_NAME, $body);
    }

    public function testNonAdminForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/admin/souteze');
        self::assertResponseStatusCodeSame(403);
    }
}
