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

    /** @var array{env: array{bool, string|null}, server: array{bool, string|null}, getenv: string|false} */
    private array $flagSnapshot;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests flip a process-global env var. Snapshot its exact state up front so
        // tearDown can restore it — never leaking a set OR unset value into sibling tests
        // (unsetting the .env baseline used to 500 every later test that boots Twig).
        $this->flagSnapshot = [
            'env' => [\array_key_exists(self::ENV_FLAG, $_ENV), $_ENV[self::ENV_FLAG] ?? null],
            'server' => [\array_key_exists(self::ENV_FLAG, $_SERVER), $_SERVER[self::ENV_FLAG] ?? null],
            'getenv' => getenv(self::ENV_FLAG),
        ];
    }

    protected function tearDown(): void
    {
        [$envSet, $envValue] = $this->flagSnapshot['env'];
        if ($envSet) {
            $_ENV[self::ENV_FLAG] = $envValue;
        } else {
            unset($_ENV[self::ENV_FLAG]);
        }

        [$serverSet, $serverValue] = $this->flagSnapshot['server'];
        if ($serverSet) {
            $_SERVER[self::ENV_FLAG] = $serverValue;
        } else {
            unset($_SERVER[self::ENV_FLAG]);
        }

        $getenv = $this->flagSnapshot['getenv'];
        putenv(false === $getenv ? self::ENV_FLAG : self::ENV_FLAG.'='.$getenv);

        parent::tearDown();
    }

    public function testTeaserHiddenByDefault(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID,
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
            '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID,
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
