<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationFlowTest extends WebTestCase
{
    public function testSuccessfulRegistrationRedirectsToPending(): void
    {
        $client = static::createClient();

        $client->request('GET', '/registrace');
        self::assertResponseIsSuccessful();

        $client->submitForm('Vytvořit účet', [
            'registration_form[email]' => 'newuser@example.com',
            'registration_form[nickname]' => 'newuser123',
            'registration_form[password][first]' => 'Securepassword1',
            'registration_form[password][second]' => 'Securepassword1',
        ]);

        self::assertResponseRedirects('/overeni-ceka');
    }

    public function testDuplicateEmailShowsError(): void
    {
        $client = static::createClient();

        $client->request('GET', '/registrace');
        $client->submitForm('Vytvořit účet', [
            'registration_form[email]' => AppFixtures::VERIFIED_USER_EMAIL,
            'registration_form[nickname]' => 'uniquenick99',
            'registration_form[password][first]' => 'Password1!',
            'registration_form[password][second]' => 'Password1!',
        ]);

        // Form re-renders with error (422 when validation/business fails)
        self::assertSame(422, $client->getResponse()->getStatusCode());
        self::assertSelectorExists('[id="registration_form_email"]');
    }

    public function testDuplicateNicknameShowsError(): void
    {
        $client = static::createClient();

        $client->request('GET', '/registrace');
        $client->submitForm('Vytvořit účet', [
            'registration_form[email]' => 'brand_new@example.com',
            'registration_form[nickname]' => AppFixtures::VERIFIED_USER_NICKNAME,
            'registration_form[password][first]' => 'Password1!',
            'registration_form[password][second]' => 'Password1!',
        ]);

        self::assertSame(422, $client->getResponse()->getStatusCode());
    }

    public function testInvalidNicknameRegexRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/registrace');
        $client->submitForm('Vytvořit účet', [
            'registration_form[email]' => 'valid@example.com',
            'registration_form[nickname]' => 'invalid nick!',
            'registration_form[password][first]' => 'Password1!',
            'registration_form[password][second]' => 'Password1!',
        ]);

        self::assertSame(422, $client->getResponse()->getStatusCode());
    }
}
