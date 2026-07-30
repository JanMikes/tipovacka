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
        yield 'profil' => ['/profil'];
        yield 'kredity' => ['/kredity'];
        yield 'oznámení' => ['/oznameni'];
        yield 'žebříček' => ['/zebricek'];
        yield 'nová soutěž' => ['/souteze/nova'];
        yield 'detail soutěže' => ['/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID];
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
        yield 'nákup kreditů' => ['/kredity/koupit', 'POST'];
        yield 'připojení do globální soutěže' => ['/souteze/'.AppFixtures::GLOBAL_COMPETITION_ID.'/pripojit-se', 'POST'];
        yield 'opuštění soutěže' => ['/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/opustit', 'POST'];
        yield 'označení oznámení' => ['/oznameni/precteno', 'POST'];
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
        // B15 turned the PIN page into a landing like the two invitation ones: reachable,
        // because it must onboard people who have no account at all. Reaching it is not
        // joining through it — testUnverifiedUserStillCannotJoinByPin pins that.
        yield 'připojení PINem' => ['/pripojit'];
        yield 'FAQ' => ['/faq'];
        yield 'ceník' => ['/cenik'];
        yield 'ochrana soukromí' => ['/ochrana-soukromi'];
        yield 'smazání účtu' => ['/ucet/smazat'];
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

        self::assertResponseRedirects('/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'?pripojeno=1');
    }

    /**
     * B15/B14 — a PIN is typeable while unverified, but it may only be *remembered*.
     */
    public function testUnverifiedUserStillCannotJoinByPin(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request('GET', '/pripojit');
        $client->submitForm('Pokračovat', ['join_by_pin_form[pin]' => AppFixtures::VERIFIED_COMPETITION_PIN]);

        self::assertResponseRedirects(self::AIRLOCK);

        $em = $this->entityManager($client);
        $em->clear();
        $memberships = $em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM memberships WHERE user_id = :u AND competition_id = :c',
            ['u' => AppFixtures::UNVERIFIED_USER_ID, 'c' => AppFixtures::VERIFIED_COMPETITION_ID],
        );

        self::assertSame(0, (int) $memberships, 'A PIN must not buy a membership before the e-mail is verified.');
    }

    /**
     * B14 — the guard, exercised the way a browser does it.
     *
     * Every other test here logs in with `KernelBrowser::loginUser()`, which primes the
     * token storage directly. That is exactly the shape that hid B1: the subscriber ran
     * BEFORE the firewall, so `Security::getUser()` was empty on every real request while
     * the tests stayed green. A real form login followed by several navigations is the
     * only version of this assertion that can fail when the ordering breaks again.
     */
    public function testTheGuardHoldsAfterARealFormLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/prihlaseni');
        $client->submitForm('Přihlásit se', [
            '_username' => AppFixtures::UNVERIFIED_USER_EMAIL,
            '_password' => AppFixtures::DEFAULT_PASSWORD,
        ]);
        self::assertResponseRedirects(self::AIRLOCK);

        foreach (['/nastenka', '/zapasy', '/profil', '/kredity', '/oznameni', '/zebricek', '/souteze/nova'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects(self::AIRLOCK, message: $path.' must bounce even on a later request.');
        }
    }

    /**
     * B14 — an account that may go nowhere must not be shown a bar full of places to go.
     * The guard was holding all along; the app-variant nav made it look otherwise.
     */
    public function testAirlockRendersWithoutTheAppChrome(): void
    {
        $client = static::createClient();
        $this->loginUnverified($client);

        $client->request('GET', self::AIRLOCK);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/nastenka"]');
        self::assertSelectorNotExists('a[href="/souteze"]');
        self::assertSelectorNotExists('a[href="/zebricek"]');
        self::assertSelectorNotExists('a[href="/souteze/nova"]');
        self::assertSelectorNotExists('a[href="/profil"]');
        self::assertSelectorNotExists('nav.primary');
        // The two ways out stay.
        self::assertSelectorExists('form[action="/overeni-ceka/znovu-odeslat"]');
        self::assertSelectorExists('a[href="/odhlaseni"]');
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
        yield 'profil' => ['/profil'];
        yield 'kredity' => ['/kredity'];
        yield 'detail soutěže' => ['/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID];
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
