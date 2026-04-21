<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class PasswordResetFlowTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testRequestPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-hesla');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="request_password_reset_form[email]"]');
    }

    public function testPasswordResetRequestForUnknownEmailRedirects(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RequestPasswordResetForm');
        $response = $component->submitForm([
            'request_password_reset_form' => ['email' => 'nobody@nowhere.com'],
        ], 'submit')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/reset-hesla/email-odeslan', $response->headers->get('Location'));
    }

    public function testPasswordResetRequestForExistingUserRedirects(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RequestPasswordResetForm');
        $response = $component->submitForm([
            'request_password_reset_form' => ['email' => AppFixtures::VERIFIED_USER_EMAIL],
        ], 'submit')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/reset-hesla/email-odeslan', $response->headers->get('Location'));
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
