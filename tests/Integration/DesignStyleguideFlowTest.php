<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class DesignStyleguideFlowTest extends WebTestCase
{
    public function testAdminCanViewStyleguide(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/_design');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Design — reference');
        // A „Připravujeme" reference label renders on the page.
        self::assertSelectorTextContains('body', 'Připravujeme');
    }

    public function testStyleguideRendersBothSoutezSwitcherVariants(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/_design');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);

        // The grouped picker: live group first, finished second, source name + Prague date range.
        self::assertStringContainsString('<optgroup label="Probíhající">', $body);
        self::assertStringContainsString('<optgroup label="Ukončené">', $body);
        self::assertLessThan(
            strpos($body, '<optgroup label="Ukončené">'),
            strpos($body, '<optgroup label="Probíhající">'),
        );
        self::assertStringContainsString('data-meta="2. 6. 2026 – 11. 6. 2026"', $body);
        self::assertStringContainsString('data-sub="MS ve fotbale 2026"', $body);

        // The no-JS affordance ships with the control.
        self::assertStringContainsString('<noscript>', $body);

        // Exactly one soutěž renders a static chip, not a second form.
        self::assertSame(1, substr_count($body, 'action="/_design"'));
        self::assertStringContainsString('Jediná soutěž', $body);
    }

    public function testVerifiedNonAdminUserReceivesForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $client->loginUser($verified);

        $client->request('GET', '/_design');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/_design');

        self::assertResponseStatusCodeSame(302);
    }
}
