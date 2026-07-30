<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;

final class NavigationTest extends WebTestCase
{
    public function testAnonymousSeesLoginAndRegisterLinks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/prihlaseni');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/prihlaseni"]');
        self::assertSelectorExists('a[href="/registrace"]');
    }

    /**
     * Item 05 added „Žebříček" to the logged-out bar — the page it points at is now a
     * real, publicly viewable board rather than a members-only redirector.
     */
    public function testAnonymousTopBarHasSoutezeAndZebricek(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                ['Soutěže', '/souteze'],
                ['Žebříček', '/zebricek'],
            ],
            $crawler->filter('header.wtnav nav.primary a')->each(
                static fn (Crawler $node): array => [trim($node->text()), $node->attr('href')],
            ),
        );
        // …and it is genuinely reachable without logging in.
        $client->request('GET', '/zebricek');
        self::assertResponseIsSuccessful();
        // The marketing pages are footer-only now; they must not be back in the bar.
        self::assertSelectorNotExists('header.wtnav nav.primary a[href="/funkce"]');
        self::assertSelectorNotExists('header.wtnav nav.primary a[href="/cenik"]');
        self::assertSelectorNotExists('header.wtnav nav.primary a[href="/pro-firmy"]');
        self::assertSelectorNotExists('header.wtnav nav.primary a[href="/faq"]');
        // Primary CTA points at registration.
        self::assertSelectorExists('header.wtnav .actions a.btn-primary[href="/registrace"]');
        // Item 17: both labels stay in the markup — CSS shows exactly one of them at any
        // width. Before, the fallback flipped at 768 while the long one hid at 900, so the
        // button rendered with NO label at all between those two widths.
        self::assertSelectorExists('header.wtnav .actions a.btn-primary[href="/registrace"] .cta-label');
        self::assertSelectorExists('header.wtnav .actions a.btn-primary[href="/registrace"] .cta-short');
        // The wallet is nobody's business until you have one.
        self::assertSelectorNotExists('header.wtnav .credit-chip');
        self::assertSelectorNotExists('header.wtnav a[href^="/kredity"]');
    }

    /**
     * Item 17 — the exact hamburger link set of the logged-out bar. „Vytvořit soutěž" is
     * absent: there is no account to create a soutěž with.
     */
    public function testAnonymousMobileMenuLinkSet(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                ['Soutěže', '/souteze'],
                ['Žebříček', '/zebricek'],
                ['Přihlásit se', '/prihlaseni'],
                ['Registrace zdarma', '/registrace'],
            ],
            $crawler->filter('header.wtnav .wt-mobile a')->each(
                static fn (Crawler $node): array => [trim($node->text()), $node->attr('href')],
            ),
        );
    }

    public function testAuthenticatedTopBarHasTheThreePrimaryLinks(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                ['Nástěnka hráče', '/nastenka'],
                ['Soutěže', '/souteze'],
                ['Žebříček', '/zebricek'],
            ],
            $crawler->filter('header.wtnav nav.primary a')->each(
                static fn (Crawler $node): array => [trim($node->text()), $node->attr('href')],
            ),
        );
        // „Zápasy" left the bar — the page itself stays reachable by URL.
        self::assertSelectorNotExists('header.wtnav a[href="/zapasy"]');
        $client->request('GET', '/zapasy');
        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function deIndexedMarketingPages(): iterable
    {
        yield '/funkce' => ['/funkce'];
        yield '/cenik' => ['/cenik'];
        yield '/pro-firmy' => ['/pro-firmy'];
        yield '/faq' => ['/faq'];
    }

    #[DataProvider('deIndexedMarketingPages')]
    public function testDeLinkedMarketingPagesAreNoIndex(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
    }

    public function testHomepageIsNotDeIndexed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
    }

    public function testAuthenticatedSeesProfileAndLogoutLinks(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/profil"]');
        self::assertSelectorExists('a[href="/odhlaseni"]');
    }

    /**
     * Item 17 — the wallet balance is in the bar itself and one tap from dobití.
     * `credits_buy` (/kredity/koupit) is the POST-only Stripe action, so the chip points at
     * the credits page and the „Dobít kredity" card on it.
     */
    public function testAuthenticatedBarCarriesTheCreditBalance(): void
    {
        $client = static::createClient();
        $client->loginUser($this->verifiedUser($client));

        $crawler = $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        $chip = $crawler->filter('header.wtnav .actions a.credit-chip');
        self::assertCount(1, $chip, 'exactly one balance in the chrome — one wallet query');
        self::assertSame('/kredity#dobit', $chip->attr('href'));
        self::assertStringStartsWith('Kredity: ', (string) $chip->attr('aria-label'));
        self::assertSame('0', trim($chip->text()), 'the AppFixtures user has no wallet yet');

        // …and the card it points at exists.
        $client->request('GET', '/kredity');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#dobit');
    }

    /**
     * Item 17 — the exact hamburger link set of the logged-in bar. „Vytvořit soutěž" moved
     * in here because the bar drops it below 900 px (B20), and „Kredity" stays reachable.
     */
    public function testAuthenticatedMobileMenuLinkSet(): void
    {
        $client = static::createClient();
        $client->loginUser($this->verifiedUser($client));

        $crawler = $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                ['Nástěnka hráče', '/nastenka'],
                ['Soutěže', '/souteze'],
                ['Žebříček', '/zebricek'],
                ['Vytvořit soutěž', '/souteze/nova'],
                ['Profil', '/profil'],
                ['Kredity', '/kredity'],
                ['Odhlásit se', '/odhlaseni'],
            ],
            $crawler->filter('header.wtnav .wt-mobile a')->each(
                static fn (Crawler $node): array => [trim($node->text()), $node->attr('href')],
            ),
        );
    }

    /**
     * B14's airlock must stay chrome-free — a balance there would advertise a page the
     * guard immediately bounces the user off, which is the bug B14 closed.
     */
    public function testVerificationAirlockCarriesNoCreditBalance(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $unverified = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertNotNull($unverified);
        $client->loginUser($unverified);

        $client->request('GET', '/overeni-ceka');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.credit-chip');
        self::assertSelectorNotExists('a[href^="/kredity"]');
        self::assertSelectorNotExists('nav.primary');
        self::assertSelectorNotExists('.wt-mobile');
        self::assertSelectorNotExists('a[href="/souteze/nova"]');
    }

    public function testAdminSeesAdminLink(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href^="/admin/turnaje"]');
    }

    private function verifiedUser(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);

        return $user;
    }
}
