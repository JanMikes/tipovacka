<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Membership;
use App\Tests\Support\WebFlowHelpers;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Uid\Uuid;

/**
 * „Nástěnka hráče" (item 06) — one soutěž in focus, chosen with the switcher.
 */
final class DashboardFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    public function testAnonymousRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/nastenka');

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testEverySectionRenders(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Ahoj, ', $body);
        self::assertStringContainsString('Zobrazená soutěž', $body);
        self::assertStringContainsString('Poslední Tvoje tipy', $body);
        self::assertStringContainsString('Moje soutěže', $body);
        self::assertStringContainsString('Následující zápasy', $body);
        self::assertStringContainsString('Odehrané zápasy', $body);
        self::assertStringContainsString('Žebříček', $body);
        // „Celý žebříček" points at item 05's standalone page, never the deleted
        // competition-scoped route.
        self::assertSelectorExists('a[href="/zebricek?soutez='.AppFixtures::VERIFIED_COMPETITION_ID.'"]');
    }

    /**
     * Product owner, 2026-07-29: the PIN bar belongs on /souteze, not here. The
     * partial, the controller and the route all stay — only this page drops it.
     */
    public function testJoinByPinBarIsGone(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action="/pripojit/rychle"]'));
        self::assertStringNotContainsString('Připojit se k soutěži', (string) $client->getResponse()->getContent());
        // …but the bar itself still works where it lives now.
        $client->request('GET', '/souteze');
        self::assertResponseIsSuccessful();
    }

    /** These three sections moved to /souteze (item 07) or died with the stat cards. */
    public function testRetiredSectionsAreGone(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/nastenka');

        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Moje zdroje zápasů', $body);
        self::assertStringNotContainsString('Objev další soutěže', $body);
        self::assertStringNotContainsString('VYHODNOCENÉ TIPY', $body);
    }

    public function testSwitcherScopesThePage(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('GET', '/nastenka?soutez='.AppFixtures::PREMIUM_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hero-rank-pool', AppFixtures::PREMIUM_COMPETITION_NAME);

        $client->request('GET', '/nastenka?soutez='.AppFixtures::BOOSTS_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hero-rank-pool', AppFixtures::BOOSTS_COMPETITION_NAME);
    }

    /**
     * A foreign or malformed id falls back silently — guessing a UUID must never
     * reveal that it exists, let alone anything inside it.
     */
    public function testForeignOrUnknownCompetitionFallsBackWithoutLeaking(): void
    {
        $client = static::createClient();
        // VERIFIED_USER is deliberately NOT a member of BOOSTS_COMPETITION.
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        foreach ([AppFixtures::BOOSTS_COMPETITION_ID, '00000000-0000-7000-8000-000000000000', 'neco'] as $requested) {
            $client->request('GET', '/nastenka?soutez='.$requested);

            self::assertResponseIsSuccessful();
            $body = (string) $client->getResponse()->getContent();
            self::assertStringContainsString(AppFixtures::VERIFIED_COMPETITION_NAME, $body);
            self::assertStringNotContainsString(AppFixtures::BOOSTS_COMPETITION_NAME, $body);
        }
    }

    /** Zero soutěží: a CTA to start one, not a broken hero. */
    public function testUserInNoCompetitionGetsTheEmptyState(): void
    {
        $client = static::createClient();
        $this->leaveEveryCompetition(AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Zatím nehraješ v žádné soutěži', $body);
        self::assertSelectorExists('a[href="/souteze/nova"]');
        // Nothing that needs a soutěž may render.
        self::assertCount(0, $crawler->filter('.hero-rank'));
        self::assertStringNotContainsString('Následující zápasy', $body);
    }

    /** Exactly one soutěž: the switcher's static chip, never a dropdown. */
    public function testSingleCompetitionRendersTheStaticChip(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#soutez-switcher-dashboard'));
        self::assertStringContainsString(AppFixtures::VERIFIED_COMPETITION_NAME, (string) $client->getResponse()->getContent());
    }

    public function testSeveralCompetitionsRenderTheDropdown(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#soutez-switcher-dashboard'));
    }

    /**
     * Item 18 — the „SOUTĚŽ" roletka is gone, so the Nástěnka is ALWAYS scoped to the
     * soutěž in focus. `?zapasy=vse` must be inert: no control, and no match from
     * another soutěž leaking into the lists.
     */
    public function testCompetitionScopeRoletkaIsGoneAndZapasyParamIsInert(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $scoped = '/nastenka?soutez='.AppFixtures::PREMIUM_COMPETITION_ID;
        $crawler = $client->request('GET', $scoped);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('select[name="zapasy"]'));
        $withParam = $client->request('GET', $scoped.'&zapasy=vse');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $withParam->filter('select[name="zapasy"]'));

        // The same page either way — the parameter changes nothing at all.
        self::assertCount(
            $crawler->filter('[data-reveal-target="item"]')->count(),
            $withParam->filter('[data-reveal-target="item"]'),
        );
    }

    /**
     * Item 18 — one filter state, two shapes: chips from the desktop breakpoint up, a
     * roletka below it. Both are plain HTML over the same `?filtr=`, so both work with
     * JavaScript off (the roletka carries its own „Použít" submit).
     */
    public function testFiltersRenderAsChipsAndAsANoJsDropdown(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', '/nastenka?soutez='.AppFixtures::PUBLIC_COMPETITION_ID.'&filtr=ukoncene');
        self::assertResponseIsSuccessful();

        self::assertCount(5, $crawler->filter('.lb-tab.has-count'));
        self::assertCount(1, $crawler->filter('.lb-tab.has-count.active'));

        $select = $crawler->filter('form.mf-scope[action="/nastenka"] select#dashboard-filtr');
        self::assertCount(1, $select);
        self::assertCount(5, $select->filter('option'));
        self::assertSame('ukoncene', $select->filter('option[selected]')->attr('value'));
        // No JavaScript needed: the roletka carries its own always-visible submit.
        self::assertCount(1, $crawler->filter('form.mf-scope button[type="submit"]'));
        // The soutěž in focus survives the submit, or the roletka would reset the page.
        self::assertSame(
            AppFixtures::PUBLIC_COMPETITION_ID,
            $crawler->filter('form.mf-scope input[name="soutez"]')->attr('value'),
        );
    }

    /** Acceptance criterion 5: every chip renders exactly as many rows as it counts. */
    public function testFilterChipCountsMatchWhatTheyFilterTo(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', '/nastenka?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertResponseIsSuccessful();

        $counts = [];
        $crawler->filter('.lb-tab.has-count')->each(static function (Crawler $chip) use (&$counts): void {
            $href = (string) $chip->attr('href');
            preg_match('/filtr=([a-z]+)/', $href, $m);
            $counts[$m[1] ?? 'vse'] = (int) $chip->filter('.mf-count')->text();
        });

        self::assertArrayHasKey('live', $counts);
        self::assertArrayHasKey('ukoncene', $counts);

        foreach ($counts as $filter => $expected) {
            $filtered = $client->request(
                'GET',
                '/nastenka?soutez='.AppFixtures::PUBLIC_COMPETITION_ID.'&filtr='.$filter,
            );
            self::assertResponseIsSuccessful();
            self::assertCount(
                $expected,
                $filtered->filter('[data-reveal-target="item"]'),
                sprintf('Chip „%s" counts %d matches but the list renders a different number.', $filter, $expected),
            );
        }
    }

    /**
     * Acceptance criterion 4 — „Rozložení tipů" is resolved ONCE per page by
     * TipStatsProvider. A per-row resolve would make the query count grow with the
     * number of matches; this pins that it does not.
     */
    public function testTipStatsCostDoesNotGrowWithTheNumberOfMatches(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        // PUBLIC_COMPETITION carries several matches, GLOBAL_COMPETITION shares the
        // same source; a bounded provider spends the same on both.
        $small = $this->queryCount($client, '/nastenka?soutez='.AppFixtures::VERIFIED_COMPETITION_ID);
        $large = $this->queryCount($client, '/nastenka?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);

        self::assertGreaterThan(0, $small);
        self::assertLessThanOrEqual(
            $small + 12,
            $large,
            'The Nástěnka must batch „Rozložení tipů" — a per-match query would scale with the match count.',
        );
    }

    public function testUpcomingMatchShowsUzamcenoPillWhenCompetitionLocked(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        // Before lock, the (late-added) upcoming Tygři match is tippable ⇒ „Chybí tip".
        $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Tygři', $body);
        self::assertStringContainsString('Chybí tip', $body);

        // Lock the competition — the match becomes non-late-added and locked.
        $em = $this->testEntityManager();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertNotNull($competition);
        $competition->lockTips(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $competition->popEvents();
        $em->flush();

        $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Tygři', $body);
        // Locked-and-untipped ⇒ „Uzamčeno", never „Tip odeslán" for a match with no tip.
        self::assertStringContainsString('Uzamčeno', $body);
        self::assertStringNotContainsString('Chybí tip', $body);
        self::assertStringNotContainsString('Tip odeslán', $body);
    }

    private function queryCount(KernelBrowser $client, string $path): int
    {
        $client->enableProfiler();
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);

        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    private function leaveEveryCompetition(string $userId): void
    {
        $this->testEntityManager()
            ->createQuery('UPDATE '.Membership::class.' m SET m.leftAt = :now WHERE m.user = :user')
            ->setParameter('now', new \DateTimeImmutable('2025-06-15 12:00:00 UTC'))
            ->setParameter('user', Uuid::fromString($userId))
            ->execute();
    }
}
