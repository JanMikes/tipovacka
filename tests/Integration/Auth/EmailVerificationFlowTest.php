<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
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

    public function testTamperedTokenShowsResendCta(): void
    {
        $client = static::createClient();
        $signedUrl = $this->buildSignedVerificationUrl(
            $client,
            AppFixtures::UNVERIFIED_USER_ID,
            AppFixtures::UNVERIFIED_USER_EMAIL,
        );

        // A tampered token kills both the full-URI signature AND the token
        // fallback — this is the genuinely unrecoverable link.
        $tampered = preg_replace('/(token=)([^&]+)/', '$1zzzzzzzzzz', $signedUrl);
        self::assertIsString($tampered);

        $client->request('GET', $tampered);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Odkaz je neplatný');
        // The "Vyžádat nový" CTA must show so the user has a recovery path.
        self::assertSelectorExists('a[href="/overeni-ceka"]');

        // A rejected link is an expected outcome (bots crawl one-time links, users
        // click stale ones). Anything logged at ERROR here becomes a Sentry issue
        // in prod — that regression was TIPOVACKA-K. Asserting the warning IS
        // present proves the handler captures, so the no-errors assert cannot
        // pass vacuously.
        $capture = $client->getContainer()->get('monolog.handler.capture');
        self::assertInstanceOf(TestHandler::class, $capture);
        self::assertTrue($capture->hasWarningThatContains('Email verification link rejected'));
        self::assertFalse($capture->hasErrorRecords());
        self::assertFalse($capture->hasCriticalRecords());
    }

    public function testScannerMangledUrlWithIntactTokenStillVerifies(): void
    {
        $client = static::createClient();
        $signedUrl = $this->buildSignedVerificationUrl(
            $client,
            AppFixtures::UNVERIFIED_USER_ID,
            AppFixtures::UNVERIFIED_USER_EMAIL,
        );

        // A link-rewriting intermediary (mail-scanner „safe links", the Seznam
        // app — TIPOVACKA-K) appends its own param: the full-URI signature dies,
        // the mail's token survives. The click must still verify.
        $client->request('GET', $signedUrl.'&utm_source=mail-scanner');
        self::assertResponseRedirects('/nastenka');

        $em = $this->entityManager($client);
        $em->clear();
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertNotNull($user);
        self::assertTrue($user->isVerified);

        $capture = $client->getContainer()->get('monolog.handler.capture');
        self::assertInstanceOf(TestHandler::class, $capture);
        self::assertTrue($capture->hasInfoThatContains('Email verification accepted via token fallback'));
        self::assertFalse($capture->hasErrorRecords());
    }

    public function testBrokenLinkForVerifiedUserSaysAlreadyVerified(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/overit-email?id='.AppFixtures::VERIFIED_USER_ID.'&token=mangled&signature=mangled&expires=1',
        );

        // However broken the URL, a verified account never sees a broken-link
        // error — and never gets a session out of it either.
        self::assertResponseRedirects('/prihlaseni');
        $client->request('GET', '/nastenka');
        self::assertResponseRedirects('/prihlaseni');
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
            'UPDATE competition_invitations SET email = :email WHERE id = :id',
            ['email' => AppFixtures::UNVERIFIED_USER_EMAIL, 'id' => AppFixtures::PENDING_INVITATION_ID],
        );
        $em->clear();

        $client->loginUser(
            $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID))
                ?? self::fail('User vanished'),
        );
        $client->request('GET', '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN);

        self::assertResponseRedirects('/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'?pripojeno=1');

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
