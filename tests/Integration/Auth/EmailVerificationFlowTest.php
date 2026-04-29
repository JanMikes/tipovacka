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

    public function testTamperedSignatureShowsResendCta(): void
    {
        $client = static::createClient();
        $signedUrl = $this->buildSignedVerificationUrl(
            $client,
            AppFixtures::UNVERIFIED_USER_ID,
            AppFixtures::UNVERIFIED_USER_EMAIL,
        );

        $tampered = preg_replace('/(signature=)([^&]+)/', '$1zzzzzzzzzz', $signedUrl);
        self::assertIsString($tampered);

        $client->request('GET', $tampered);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Odkaz je neplatný');
        // The "Vyžádat nový" CTA must show so the user has a recovery path.
        self::assertSelectorExists('a[href="/overeni-ceka"]');
    }

    public function testVerificationLeavesNoStaleSessionForUnknownUserId(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/overit-email?id=00000000-0000-7000-8000-000000000000&token=whatever&signature=whatever&expires=99999999999',
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Odkaz je neplatný');
    }

    public function testUnverifiedExistingUserLoggingInViaEmailInvitationGetsVerifiedAndJoined(): void
    {
        // Mirrors the "I clicked the invitation but it told me to verify email" scenario:
        // an existing password account, still unverified, follows an email invitation
        // addressed to its own mailbox. Receiving the invite proves email ownership,
        // so we accept + auto-verify rather than gating on the verification link.
        $client = static::createClient();
        $em = $this->entityManager($client);

        $unverified = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertNotNull($unverified);
        self::assertFalse($unverified->isVerified);

        // Repoint the existing pending invitation at the unverified user's email.
        $em->getConnection()->executeStatement(
            'UPDATE group_invitations SET email = :email WHERE id = :id',
            ['email' => AppFixtures::UNVERIFIED_USER_EMAIL, 'id' => AppFixtures::PENDING_INVITATION_ID],
        );
        $em->clear();

        $client->loginUser(
            $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID))
                ?? self::fail('User vanished'),
        );
        $client->request('GET', '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN);

        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID);

        $em->clear();
        $reloaded = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isVerified, 'Accepting an email invitation must verify the user.');
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
