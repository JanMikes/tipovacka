<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class LoginFlowTest extends WebTestCase
{
    public function testVerifiedUserCanLogin(): void
    {
        $client = static::createClient();
        $client->request('POST', '/prihlaseni', [
            '_username' => AppFixtures::VERIFIED_USER_EMAIL,
            '_password' => AppFixtures::DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects('/nastenka');
    }

    public function testUnverifiedUserRedirectedToVerifyPending(): void
    {
        $client = static::createClient();
        $client->request('POST', '/prihlaseni', [
            '_username' => AppFixtures::UNVERIFIED_USER_EMAIL,
            '_password' => AppFixtures::DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects('/overeni-ceka');
    }

    public function testDeletedUserCannotLogin(): void
    {
        $client = static::createClient();
        $client->request('POST', '/prihlaseni', [
            '_username' => AppFixtures::DELETED_USER_EMAIL,
            '_password' => AppFixtures::DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects('/prihlaseni');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'smazán');
    }

    public function testBlockedUserCannotLogin(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $user->deactivate(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $em->flush();

        $client->request('POST', '/prihlaseni', [
            '_username' => AppFixtures::VERIFIED_USER_EMAIL,
            '_password' => AppFixtures::DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects('/prihlaseni');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'zablokován');
    }

    /**
     * A wrong password names the next step (the reset, the sign-up), keeps the typed
     * e-mail and marks the password field — the form-feedback controller takes the
     * visitor straight to it.
     */
    public function testInvalidCredentialsSayWhatToDoNext(): void
    {
        $client = static::createClient();
        $client->request('POST', '/prihlaseni', [
            '_username' => AppFixtures::VERIFIED_USER_EMAIL,
            '_password' => 'wrongpassword',
        ]);

        self::assertResponseRedirects('/prihlaseni');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('[role="alert"]', 'Nesprávný e-mail nebo heslo.');
        self::assertCount(1, $crawler->filter('[role="alert"] a[href="/reset-hesla"]'));
        self::assertCount(1, $crawler->filter('[role="alert"] a[href="/registrace"]'));
        self::assertSame(AppFixtures::VERIFIED_USER_EMAIL, $crawler->filter('input[name="_username"]')->attr('value'));
        self::assertSame('true', $crawler->filter('input[name="_password"]')->attr('aria-invalid'));
        self::assertNull($crawler->filter('input[name="_username"]')->attr('aria-invalid'));
        self::assertCount(1, $crawler->filter('form[data-controller~="form-feedback"]'));
    }

    /**
     * An unknown address reads exactly like a wrong password — the page must not reveal
     * who has an account.
     */
    public function testUnknownEmailIsIndistinguishableFromWrongPassword(): void
    {
        $client = static::createClient();
        $client->request('POST', '/prihlaseni', [
            '_username' => 'nikdo@example.test',
            '_password' => 'wrongpassword',
        ]);

        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Nesprávný e-mail nebo heslo.');
    }

    public function testClosedAccountOffersTheContact(): void
    {
        $client = static::createClient();
        $client->request('POST', '/prihlaseni', [
            '_username' => AppFixtures::DELETED_USER_EMAIL,
            '_password' => AppFixtures::DEFAULT_PASSWORD,
        ]);

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Tento účet byl smazán.');
        self::assertCount(1, $crawler->filter('[role="alert"] a[href="mailto:kontakt@wtips.cz"]'));
        // Nothing the visitor typed is wrong — no field is marked.
        self::assertCount(0, $crawler->filter('[aria-invalid="true"]'));
    }

    public function testCleanLoginPageMarksNothing(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/prihlaseni');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[role="alert"]'));
        self::assertSame('username', $crawler->filter('input[name="_username"]')->attr('autocomplete'));
        self::assertSame('current-password', $crawler->filter('input[name="_password"]')->attr('autocomplete'));
    }
}
