<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PasswordResetFlowTest extends WebTestCase
{
    public function testPasswordResetRequestAlwaysRedirects(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-hesla');
        self::assertResponseIsSuccessful();

        $client->submitForm('Odeslat odkaz pro obnovení', [
            'request_password_reset_form[email]' => 'nobody@nowhere.com',
        ]);

        self::assertResponseRedirects('/reset-hesla/email-odeslan');
    }

    public function testPasswordResetRequestForExistingUserRedirects(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-hesla');

        $client->submitForm('Odeslat odkaz pro obnovení', [
            'request_password_reset_form[email]' => AppFixtures::VERIFIED_USER_EMAIL,
        ]);

        self::assertResponseRedirects('/reset-hesla/email-odeslan');
    }

    public function testCheckEmailPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-hesla/email-odeslan');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Zkontroluj');
    }

    public function testInvalidTokenShowsError(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-hesla/token/invalidtoken123');
        // Controller stores token in session and redirects to /reset-hesla/nove
        self::assertResponseRedirects('/reset-hesla/nove');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'neplatný');
    }
}
