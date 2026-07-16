<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Command\RequestPasswordReset\RequestPasswordResetCommand;
use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
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

    public function testPasswordResetRequestForUnknownEmailSendsSignUpInvitation(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RequestPasswordResetForm');
        $response = $component->submitForm([
            'request_password_reset_form' => ['email' => 'nobody@nowhere.com'],
        ], 'submit')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/reset-hesla/email-odeslan', $response->headers->get('Location'));

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'nobody@nowhere.com');
        self::assertEmailHtmlBodyContains($email, '/registrace');
    }

    public function testPasswordResetRequestForDeletedUserSendsSignUpInvitation(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RequestPasswordResetForm');
        $component->submitForm([
            'request_password_reset_form' => ['email' => AppFixtures::DELETED_USER_EMAIL],
        ], 'submit');

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailHtmlBodyContains($email, '/registrace');
    }

    public function testSignUpInvitationIsRateLimitedPerEmail(): void
    {
        // Command bus directly — the test client reboots the kernel between
        // requests, which would reset the in-memory rate limiter storage.
        self::bootKernel();

        /** @var MessageBusInterface $commandBus */
        $commandBus = self::getContainer()->get('test.command.bus'); // @phpstan-ignore symfonyContainer.serviceNotFound
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound

        foreach (range(1, 3) as $attempt) {
            $commandBus->dispatch(new RequestPasswordResetCommand(email: 'bombed@nowhere.com'));
        }

        $emails = array_filter(
            $async->getSent(),
            fn ($envelope) => $envelope->getMessage() instanceof SendEmailMessage,
        );

        self::assertCount(2, $emails);
    }

    public function testPasswordResetRequestForExistingUserSendsResetEmail(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RequestPasswordResetForm');
        $response = $component->submitForm([
            'request_password_reset_form' => ['email' => AppFixtures::VERIFIED_USER_EMAIL],
        ], 'submit')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/reset-hesla/email-odeslan', $response->headers->get('Location'));

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', AppFixtures::VERIFIED_USER_EMAIL);
        self::assertEmailHtmlBodyContains($email, '/reset-hesla/token/');
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
