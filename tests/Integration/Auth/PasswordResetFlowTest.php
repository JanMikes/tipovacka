<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Command\RequestPasswordReset\RequestPasswordResetCommand;
use App\DataFixtures\AppFixtures;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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

    public function testRequestFormSaysHowToFixAMalformedEmail(): void
    {
        $client = static::createClient();
        $component = $this->createLiveComponent('Auth:RequestPasswordResetForm', [], $client);
        $client->catchExceptions(true);

        $component->submitForm([
            'request_password_reset_form' => ['email' => 'tipujici.example.test'],
        ], 'submit');

        self::assertSame(422, $component->response()->getStatusCode());
        $body = (string) $component->response()->getContent();
        self::assertStringContainsString('Tohle nevypadá jako e-mailová adresa.', $body);
        self::assertStringContainsString('aria-invalid="true"', $body);
        self::assertQueuedEmailCount(0);
    }

    public function testRequestFormOffersTheCorrectedMailboxDomain(): void
    {
        $client = static::createClient();
        $component = $this->createLiveComponent('Auth:RequestPasswordResetForm', [], $client);

        $rendered = (string) $component->set('request_password_reset_form.email', 'tipujici@seznam.cy')->render();

        self::assertStringContainsString('data-form-feedback-value-param="tipujici@seznam.cz"', $rendered);
    }

    public function testDeadResetLinkOffersAFreshOne(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-hesla/token/invalidtoken123');
        $crawler = $client->followRedirect();

        self::assertSelectorTextContains('body', 'platí 1 hodinu');
        self::assertCount(1, $crawler->filter('a[href="/reset-hesla"]:contains("Poslat nový odkaz")'));
    }

    public function testCheckEmailPageExplainsWhatToDoWhenNothingArrives(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-hesla/email-odeslan');

        self::assertSelectorTextContains('body', 'E-mail nedorazil?');
        self::assertSelectorTextContains('body', 'nejdříve za hodinu');
    }

    public function testResetFormExplainsAMismatchAtTheField(): void
    {
        $client = static::createClient();
        $this->openResetLinkFor(AppFixtures::VERIFIED_USER_EMAIL, $client);

        $component = $this->createLiveComponent('Auth:ResetPasswordForm', [], $client);
        $client->catchExceptions(true);
        $component->submitForm([
            'reset_password_form' => ['newPassword' => ['first' => 'NoveHeslo123', 'second' => 'JineHeslo123']],
        ], 'submit');

        self::assertSame(422, $component->response()->getStatusCode());
        $body = (string) $component->response()->getContent();
        self::assertStringContainsString('Hesla se neshodují. Zadejte do obou polí stejné heslo.', $body);
        self::assertStringContainsString('Alespoň 8 znaků.', $body);
    }

    public function testResetSubmittedWithoutALinkInTheSessionExplainsWhy(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-hesla');

        $component = $this->createLiveComponent('Auth:ResetPasswordForm', [], $client);
        $response = $component->submitForm([
            'reset_password_form' => ['newPassword' => ['first' => 'NoveHeslo123', 'second' => 'NoveHeslo123']],
        ], 'submit')->response();

        self::assertSame('/reset-hesla', $response->headers->get('Location'));
        $client->request('GET', '/reset-hesla');
        self::assertSelectorTextContains('body', 'Odkaz pro obnovení hesla jsme v tomto prohlížeči nenašli.');
    }

    public function testSuccessfulResetSendsToLoginWithInstructions(): void
    {
        $client = static::createClient();
        $this->openResetLinkFor(AppFixtures::VERIFIED_USER_EMAIL, $client);

        $component = $this->createLiveComponent('Auth:ResetPasswordForm', [], $client);
        $response = $component->submitForm([
            'reset_password_form' => ['newPassword' => ['first' => 'NoveHeslo123', 'second' => 'NoveHeslo123']],
        ], 'submit')->response();

        self::assertSame('/prihlaseni', $response->headers->get('Location'));
        $client->request('GET', '/prihlaseni');
        self::assertSelectorTextContains('body', 'Přihlaste se prosím novým heslem.');
    }

    private function openResetLinkFor(string $email, KernelBrowser $client): void
    {
        $this->createLiveComponent('Auth:RequestPasswordResetForm', [], $client)->submitForm([
            'request_password_reset_form' => ['email' => $email],
        ], 'submit');

        $message = self::getMailerMessage();
        self::assertInstanceOf(TemplatedEmail::class, $message);
        self::assertSame(1, preg_match('#/reset-hesla/token/([A-Za-z0-9]+)#', (string) $message->getHtmlBody(), $match));

        $client->request('GET', '/reset-hesla/token/'.$match[1]);
        self::assertResponseRedirects('/reset-hesla/nove');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }
}
