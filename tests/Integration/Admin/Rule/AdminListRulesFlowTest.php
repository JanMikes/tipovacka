<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\Rule;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AdminListRulesFlowTest extends WebTestCase
{
    public function testAdminSeesAllFourRegisteredRules(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/pravidla');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString('exact_score', $body);
        self::assertStringContainsString('correct_outcome', $body);
        self::assertStringContainsString('correct_home_goals', $body);
        self::assertStringContainsString('correct_away_goals', $body);
    }

    public function testNonAdminForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/admin/pravidla');
        self::assertResponseStatusCodeSame(403);
    }
}
