<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testAnonymousTopBarHasOnlyTheSoutezeLink(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Soutěže'], $crawler->filter('header.wtnav nav.primary a')->each(
            static fn (Crawler $node): string => trim($node->text()),
        ));
        // The marketing pages are footer-only now; they must not be back in the bar.
        self::assertSelectorNotExists('header.wtnav nav.primary a[href="/funkce"]');
        self::assertSelectorNotExists('header.wtnav nav.primary a[href="/cenik"]');
        self::assertSelectorNotExists('header.wtnav nav.primary a[href="/pro-firmy"]');
        self::assertSelectorNotExists('header.wtnav nav.primary a[href="/faq"]');
        // Primary CTA points at registration.
        self::assertSelectorExists('header.wtnav .actions a.btn-primary[href="/registrace"]');
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
}
