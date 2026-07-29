<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * B1 — a logged-in but unverified account is confined to the verification airlock.
 *
 * Covers GET pages, POST/write actions (including the Live Component endpoint, through
 * which tips and competitions are written) and the escape hatches that must keep working.
 */
final class UnverifiedEmailAirlockTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private const AIRLOCK = '/overeni-ceka';

    /**
     * @return iterable<string, array{string}>
     */
    public static function gatedGetPages(): iterable
    {
        yield 'nástěnka' => ['/nastenka'];
        yield 'zápasy' => ['/zapasy'];
        yield 'profil' => ['/portal/profil'];
        yield 'kredity' => ['/portal/kredity'];
        yield 'oznámení' => ['/portal/oznameni'];
        yield 'žebříček' => ['/portal/zebricek'];
        yield 'nová soutěž' => ['/portal/souteze/nova'];
        yield 'připojení PINem' => ['/pripojit'];
        yield 'detail soutěže' => ['/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID];
    }

    #[DataProvider('gatedGetPages')]
    public function testUnverifiedUserIsRedirectedFromPortalPages(string $path): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request('GET', $path);

        self::assertResponseRedirects(self::AIRLOCK, message: $path.' must bounce to the airlock.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function gatedPostActions(): iterable
    {
        yield 'nákup kreditů' => ['/portal/kredity/koupit', 'POST'];
        yield 'připojení do globální soutěže' => ['/portal/souteze/'.AppFixtures::GLOBAL_COMPETITION_ID.'/pripojit-se', 'POST'];
        yield 'rychlé připojení PINem' => ['/pripojit/rychle', 'POST'];
        yield 'opuštění soutěže' => ['/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/opustit', 'POST'];
        yield 'označení oznámení' => ['/portal/oznameni/precteno', 'POST'];
    }

    #[DataProvider('gatedPostActions')]
    public function testUnverifiedUserIsBlockedFromPortalWriteActions(string $path, string $method): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request($method, $path);

        self::assertResponseRedirects(self::AIRLOCK, message: $method.' '.$path.' must bounce to the airlock.');
    }

    /**
     * Every Live Component shares the single `/_components/…` route, so a path-prefix guard
     * misses it entirely — yet tips and competitions are written through exactly that route.
     */
    public function testUnverifiedUserIsBlockedFromLiveComponentWriteActions(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        foreach (['Guess:GuessSubmitForm/submit', 'Competition:CreateWizard/submit', 'Notification:Preferences/save'] as $endpoint) {
            $client->request('POST', '/_components/'.$endpoint);

            self::assertResponseRedirects(
                self::AIRLOCK,
                message: $endpoint.' must bounce to the airlock before the component ever hydrates.',
            );
        }
    }

    /**
     * The real browser request carries the live-component Accept header, which makes the UX
     * bundle rewrite our redirect into `204 + X-Live-Redirect` — the shape its JS follows.
     * Assert that shape too, otherwise the guard would only look right in test mode.
     */
    public function testBlockedLiveComponentRequestRedirectsTheJavaScriptClientToo(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request(
            'POST',
            '/_components/Guess:GuessSubmitForm/submit',
            server: ['HTTP_ACCEPT' => 'application/vnd.live-component+html'],
        );

        $response = $client->getResponse();
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('1', $response->headers->get('X-Live-Redirect'));
        self::assertSame(self::AIRLOCK, $response->headers->get('Location'));
    }

    public function testAirlockRedirectCarriesAnExplanation(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request('GET', '/nastenka');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'ověřte svou e-mailovou adresu');
    }

    public function testAirlockNamesTheAddressAndOffersResend(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request('GET', self::AIRLOCK);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::UNVERIFIED_USER_EMAIL);
        self::assertSelectorExists('form[action="/overeni-ceka/znovu-odeslat"]');
        self::assertSelectorExists('a[href="/odhlaseni"]');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedPages(): iterable
    {
        yield 'airlock' => [self::AIRLOCK];
        yield 'veřejné soutěže' => ['/souteze'];
        yield 'FAQ' => ['/faq'];
        yield 'ceník' => ['/cenik'];
        yield 'ochrana soukromí' => ['/ochrana-soukromi'];
        yield 'smazání účtu' => ['/portal/ucet/smazat'];
    }

    #[DataProvider('allowedPages')]
    public function testUnverifiedUserKeepsAccessToTheAirlockAndPublicPages(string $path): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request('GET', $path);

        self::assertResponseIsSuccessful($path.' must stay reachable while unverified.');
    }

    public function testUnverifiedUserCanResendTheVerificationEmail(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request('GET', self::AIRLOCK);
        $client->submitForm('Poslat znovu');

        self::assertResponseRedirects(self::AIRLOCK);
        self::assertQueuedEmailCount(1);
    }

    public function testUnverifiedUserCanReachTheVerificationLinkAndLogOut(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request('GET', '/overit-email');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/odhlaseni');
        self::assertResponseRedirects('/prihlaseni');
    }

    /**
     * Auth Live Components stay reachable — the airlock must not brick the anonymous-facing
     * forms that share the `/_components/…` route with the gated ones.
     */
    public function testAuthLiveComponentsStayReachable(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $response = $this->createLiveComponent('Auth:RequestPasswordResetForm')
            ->submitForm(['request_password_reset_form' => ['email' => AppFixtures::UNVERIFIED_USER_EMAIL]], 'submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/reset-hesla/email-odeslan', $response->headers->get('Location'));
    }

    /**
     * An e-mail invitation addressed to the account's own mailbox proves ownership, so the
     * landing page verifies + joins instead of bouncing (see InvitationAcceptanceService).
     */
    public function testEmailInvitationLandingIsNotSwallowedByTheAirlock(): void
    {
        $client = static::createClient();
        $em = $this->entityManager($client);

        $em->getConnection()->executeStatement(
            'UPDATE competition_invitations SET email = :email WHERE id = :id',
            ['email' => AppFixtures::UNVERIFIED_USER_EMAIL, 'id' => AppFixtures::PENDING_INVITATION_ID],
        );
        $em->clear();

        $this->loginUnverified($client);
        $client->request('GET', '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN);

        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID);
    }

    /**
     * A shareable link carries no proof of mailbox ownership, so the landing page itself
     * stores the join intent and sends the user to the airlock — it must reach that logic
     * rather than being cut off by the guard.
     */
    public function testShareableLinkLandingSendsUnverifiedUserToTheAirlock(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request('GET', '/souteze/pozvanka/'.AppFixtures::PUBLIC_COMPETITION_LINK_TOKEN);
        self::assertResponseRedirects(self::AIRLOCK);

        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'ověř');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function verifiedUserPages(): iterable
    {
        yield 'nástěnka' => ['/nastenka'];
        yield 'zápasy' => ['/zapasy'];
        yield 'profil' => ['/portal/profil'];
        yield 'kredity' => ['/portal/kredity'];
        yield 'detail soutěže' => ['/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID];
    }

    #[DataProvider('verifiedUserPages')]
    public function testVerifiedUserIsUnaffected(string $path): void
    {
        $client = static::createClient();
        $em = $this->entityManager($client);
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', $path);

        self::assertResponseIsSuccessful($path.' must stay open for a verified user.');
    }

    public function testAnonymousVisitorStillGoesToLoginNotTheAirlock(): void
    {
        $client = static::createClient();

        $client->request('GET', '/nastenka');

        self::assertResponseRedirects('/prihlaseni');
    }

    private function loginUnverified(KernelBrowser $client): void
    {
        $user = $this->entityManager($client)->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertNotNull($user);
        self::assertFalse($user->isVerified);

        $client->loginUser($user);
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}
