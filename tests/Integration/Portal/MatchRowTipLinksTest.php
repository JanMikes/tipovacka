<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;

/**
 * B7 — the match card's tip affordance must lead to the guessing surface, and when
 * tipping is closed it must go inert (nothing to navigate to) while the card still
 * reaches the match, so a locked card is never a dead end (item 11 dropped the
 * separate „Tipovat →").
 *
 * The MARKUP that satisfies this is now the SAME everywhere: item 21 retired the
 * older shape, in which the pilulka and the „+ Zadat tip" box were the links, so on
 * every surface the whole card is ONE link with a single „Zadat tip" bar inside it.
 * The invariant B7 was protecting — „a tippable card gives the user a way to reach
 * the tip form, and a locked one has no dead-looking links" — is what is asserted,
 * not the markup it used to need.
 */
final class MatchRowTipLinksTest extends WebTestCase
{
    private const string COMPETITION_URL = '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID;
    private const string GUESS_URL = self::COMPETITION_URL.'/zapasy/'.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;
    /**
     * Item 22 — every card link is soutěž-scoped now, on the cross-soutěž surfaces
     * too: VERIFIED_USER's only soutěž holding this match is VERIFIED_COMPETITION, so
     * that is the one a `/zapasy` or Nástěnka card opens.
     */
    private const string MATCH_DETAIL_URL = self::GUESS_URL;

    /**
     * Competition detail — the card links to the COMPETITION-SCOPED guessing surface.
     */
    public function testCompetitionDetailCardIsOneLinkToTheGuessingSurface(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', self::COMPETITION_URL);
        self::assertResponseIsSuccessful();

        $this->assertEveryCardIsExactlyOneLink($crawler, '#zapasy ', self::COMPETITION_URL);
        $this->assertCardOffersTheTipCta($crawler, '#zapasy ', self::GUESS_URL);
    }

    /**
     * Item 21 — `/zapasy` renders the same card, so its pilulka stopped being a link
     * of its own (there is nothing to nest it in) and the card itself carries the one
     * „Zadat tip" affordance. Item 22 pointed it at the match INSIDE a soutěž.
     */
    public function testMatchListCardIsOneLinkToTheSoutezScopedMatchPage(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/zapasy');
        self::assertResponseIsSuccessful();

        $this->assertEveryCardIsExactlyOneLink($crawler, '', '/zapasy');
        $this->assertCardOffersTheTipCta($crawler, '', self::MATCH_DETAIL_URL);
        // The state pilulka still SAYS „Chybí tip" — it just no longer links.
        self::assertStringContainsString('Chybí tip', (string) $client->getResponse()->getContent());
    }

    /**
     * Item 18 — the Nástěnka card, the shape that item 21 made universal.
     */
    public function testDashboardCardIsOneLinkWithNothingInteractiveInside(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();

        $this->assertEveryCardIsExactlyOneLink($crawler, '', '/nastenka');
        $this->assertCardOffersTheTipCta($crawler, '', self::MATCH_DETAIL_URL);
    }

    /**
     * Item 11 — a card whose competition has nothing to say about the split must
     * not keep an empty divider row. (That the strip itself sits INSIDE the card
     * is pinned by {@see TipStatsSurfacesTest}, which owns the fixture setup that
     * produces one.).
     */
    public function testNoCardKeepsAnEmptyExtraRegion(): void
    {
        $client = static::createClient();
        $this->login($client);

        foreach ([self::COMPETITION_URL, '/nastenka', '/zapasy'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();

            $crawler->filter('.tip-row-extra')->each(function (Crawler $node) use ($path): void {
                self::assertNotSame('', trim($node->text()), $path.' rendered an empty .tip-row-extra');
            });
        }
    }

    public function testLockedRowsHaveNoTipLinks(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $this->login($client);

        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        $competition->lockTips(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $competition->popEvents();
        $em->flush();

        foreach ([self::COMPETITION_URL, '/nastenka', '/zapasy'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();

            // The card still links to the match (it is ONE link), but nothing on a
            // locked card may invite a tip any more.
            self::assertGreaterThan(0, $crawler->filter('a.tip-row-link')->count(), $path.' lost its card links');
            self::assertCount(0, $crawler->filter('.tipd-cta'), $path.' must not offer „Zadat tip" once locked');
            $this->assertEveryCardIsExactlyOneLink($crawler, '', $path);
        }
    }

    /**
     * The whole card is ONE `<a>` and nothing interactive is nested inside it — the
     * „Rozložení tipů" strip IS a control, so it stays a sibling of that link.
     */
    private function assertEveryCardIsExactlyOneLink(Crawler $crawler, string $scope, string $path): void
    {
        $cards = $crawler->filter($scope.'.tip-row');
        self::assertGreaterThan(0, $cards->count(), $path.' rendered no match card');

        $cards->each(function (Crawler $card) use ($path): void {
            self::assertCount(1, $card->filter('a.tip-row-link'), $path.': a card must be exactly one link');
            self::assertCount(
                0,
                $card->filter('a.tip-row-link a, a.tip-row-link button, a.tip-row-link input, a.tip-row-link select'),
                $path.': nothing interactive may be nested inside the card link',
            );
        });
    }

    private function assertCardOffersTheTipCta(Crawler $crawler, string $scope, string $tipUrl): void
    {
        $tippable = $crawler->filter($scope.'a.tip-row-link[href="'.$tipUrl.'"]');
        self::assertCount(1, $tippable, 'Expected exactly one card linking to '.$tipUrl);
        self::assertNotNull($tippable->attr('aria-label'));
        self::assertCount(1, $tippable->filter('.tipd-cta'));
        self::assertStringContainsString('Zadat tip', $tippable->filter('.tipd-cta')->text());
    }

    private function login(KernelBrowser $client): void
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user);
    }
}
