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

    public function testInvalidCredentialsShowError(): void
    {
        $client = static::createClient();
        $client->request('POST', '/prihlaseni', [
            '_username' => 'nobody@nowhere.com',
            '_password' => 'wrongpassword',
        ]);

        self::assertResponseRedirects('/prihlaseni');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
