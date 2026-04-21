<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

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

    public function testSuccessfulVerificationLogsUserInAndRedirectsToDashboard(): void
    {
        $client = static::createClient();
        $signedUrl = $this->buildSignedVerificationUrl(
            $client,
            AppFixtures::UNVERIFIED_USER_ID,
            AppFixtures::UNVERIFIED_USER_EMAIL,
        );

        $client->request('GET', $signedUrl);
        self::assertResponseRedirects('/nastenka');

        $em = $this->entityManager($client);
        $em->clear();
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertNotNull($user);
        self::assertTrue($user->isVerified);

        $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();
    }

    public function testAlreadyVerifiedUserRedirectedToLoginWithoutSession(): void
    {
        $client = static::createClient();
        $signedUrl = $this->buildSignedVerificationUrl(
            $client,
            AppFixtures::VERIFIED_USER_ID,
            AppFixtures::VERIFIED_USER_EMAIL,
        );

        $client->request('GET', $signedUrl);
        self::assertResponseRedirects('/prihlaseni');

        $client->request('GET', '/nastenka');
        self::assertResponseRedirects('/prihlaseni');
    }

    private function buildSignedVerificationUrl(KernelBrowser $client, string $userId, string $email): string
    {
        /** @var VerifyEmailHelperInterface $helper */
        $helper = $client->getContainer()->get(VerifyEmailHelperInterface::class);

        return $helper->generateSignature(
            routeName: 'app_verify_email',
            userId: $userId,
            userEmail: $email,
            extraParams: ['id' => $userId],
        )->getSignedUrl();
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}
