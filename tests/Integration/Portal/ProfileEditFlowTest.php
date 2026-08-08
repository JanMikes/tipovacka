<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class ProfileEditFlowTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testUnauthenticatedRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profil');

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testVerifiedUserCanLoadProfilePage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getVerifiedUser($client));

        $client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'profil');
    }

    public function testVerifiedUserCanUpdateProfile(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getVerifiedUser($client));

        $component = $this->createLiveComponent('Profile:ProfileForm', [], $client);
        $response = $component->submitForm([
            'profile_form' => [
                'firstName' => 'Jan',
                'lastName' => 'Novák',
                'phone' => '+420123456789',
            ],
        ], 'save')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/profil', $response->headers->get('Location'));
    }

    public function testProfilePageOffersPasswordChange(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getVerifiedUser($client));

        $client->request('GET', '/profil');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2:contains("Heslo")', 'Heslo');
        self::assertSelectorExists('input[name="change_password_form[currentPassword]"]');
    }

    public function testVerifiedUserCanChangePassword(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getVerifiedUser($client));

        $component = $this->createLiveComponent('Profile:PasswordForm', [], $client);
        $response = $component->submitForm([
            'change_password_form' => [
                'currentPassword' => AppFixtures::DEFAULT_PASSWORD,
                'newPassword' => [
                    'first' => 'brandnewpassword123',
                    'second' => 'brandnewpassword123',
                ],
            ],
        ], 'save')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/profil', $response->headers->get('Location'));

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($this->getVerifiedUser($client), 'brandnewpassword123'));
    }

    /**
     * Changing the password rewrites the hash the session token was serialized with, which
     * is exactly what Symfony deauthenticates a session for — the user must not be thrown
     * out of the page they are standing on.
     */
    public function testSessionSurvivesPasswordChange(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getVerifiedUser($client));

        $this->createLiveComponent('Profile:PasswordForm', [], $client)->submitForm([
            'change_password_form' => [
                'currentPassword' => AppFixtures::DEFAULT_PASSWORD,
                'newPassword' => [
                    'first' => 'brandnewpassword123',
                    'second' => 'brandnewpassword123',
                ],
            ],
        ], 'save');

        $client->request('GET', '/profil');

        self::assertResponseIsSuccessful();
    }

    public function testWrongCurrentPasswordIsRejected(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getVerifiedUser($client));

        $component = $this->createLiveComponent('Profile:PasswordForm', [], $client);
        $rendered = $component->submitForm([
            'change_password_form' => [
                'currentPassword' => 'wrong-one',
                'newPassword' => [
                    'first' => 'brandnewpassword123',
                    'second' => 'brandnewpassword123',
                ],
            ],
        ], 'save')->render();

        self::assertStringContainsString('Současné heslo není správné.', (string) $rendered);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($this->getVerifiedUser($client), AppFixtures::DEFAULT_PASSWORD));
    }

    private function getVerifiedUser(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);

        return $user;
    }
}
