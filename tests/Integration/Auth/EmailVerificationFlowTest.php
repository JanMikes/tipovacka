<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmailVerificationFlowTest extends WebTestCase
{
    public function testInvalidTokenRendersErrorPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/overit-email?id='.AppFixtures::UNVERIFIED_USER_ID.'&token=invalidtoken&email='.urlencode(AppFixtures::UNVERIFIED_USER_EMAIL));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Odkaz je neplatný');
    }

    public function testMissingIdRendersErrorPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/overit-email');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Odkaz je neplatný');
    }

    public function testVerifyEmailPendingPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/overeni-ceka');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Zkontroluj');
    }
}
