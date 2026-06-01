<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class PremiumTeaserFlowTest extends WebTestCase
{
    private const string TEASER_MARKER = 'Prémiové funkce připravujeme';
    private const string ENV_FLAG = 'APP_PREMIUM_TEASER_ENABLED';

    protected function tearDown(): void
    {
        // Never leak the flag into sibling tests.
        unset($_SERVER[self::ENV_FLAG], $_ENV[self::ENV_FLAG]);
        putenv(self::ENV_FLAG);
        parent::tearDown();
    }

    public function testTeaserHiddenByDefault(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request(
            'GET',
            '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID,
        );

        self::assertResponseIsSuccessful();
        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringNotContainsString(self::TEASER_MARKER, $body);
    }

    public function testTeaserShownWhenFlagEnabled(): void
    {
        $_SERVER[self::ENV_FLAG] = '1';
        $_ENV[self::ENV_FLAG] = '1';
        putenv(self::ENV_FLAG.'=1');

        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request(
            'GET',
            '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID,
        );

        self::assertResponseIsSuccessful();
        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString(self::TEASER_MARKER, $body);
    }

    private function loginAdmin(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);
    }
}
