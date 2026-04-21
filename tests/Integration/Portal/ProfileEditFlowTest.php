<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class ProfileEditFlowTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testUnauthenticatedRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/portal/profil');

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testVerifiedUserCanLoadProfilePage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getVerifiedUser($client));

        $client->request('GET', '/portal/profil');
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
        self::assertSame('/portal/profil', $response->headers->get('Location'));
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
