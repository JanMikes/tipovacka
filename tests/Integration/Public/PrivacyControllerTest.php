<?php

declare(strict_types=1);

namespace App\Tests\Integration\Public;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PrivacyControllerTest extends WebTestCase
{
    public function testPrivacyPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ochrana-soukromi');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ochrana soukromí');
        self::assertSelectorTextContains('body', 'Komu data předáváme');
        self::assertSelectorTextContains('body', 'Jak chráníme tvé heslo');
    }

    public function testFooterLinksToPrivacyPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('footer a[href="/ochrana-soukromi"]');
    }
}
