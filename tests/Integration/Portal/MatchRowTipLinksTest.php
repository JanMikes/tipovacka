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
 * B7 — the match row's „Chybí tip" pilulka and its „+ Zadat tip" box must lead to
 * the guessing surface, the same target as the fixture itself, on EVERY surface
 * that renders `Match:MatchRow`. When tipping is closed they must be inert again
 * (nothing to navigate to) — while the fixture keeps linking to the match, so a
 * locked card is never a dead end (item 11 dropped the separate „Tipovat →").
 */
final class MatchRowTipLinksTest extends WebTestCase
{
    private const string COMPETITION_URL = '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID;
    private const string GUESS_URL = self::COMPETITION_URL.'/zapasy/'.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;
    private const string MATCH_DETAIL_URL = '/zapasy/'.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;

    public function testCompetitionDetailLinksBothTipAffordancesToTheGuessingSurface(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', self::COMPETITION_URL);
        self::assertResponseIsSuccessful();

        // The state pilulka is wrapped in a link with its own accessible name…
        $pillLink = $crawler->filter('a.tip-row-pill-link[href="'.self::GUESS_URL.'"]');
        self::assertCount(1, $pillLink);
        self::assertStringContainsString('Chybí tip', $pillLink->text());
        self::assertNotNull($pillLink->attr('aria-label'));

        // …and so is the dashed „+ Zadat tip" box.
        $promptLink = $crawler->filter('a.my-tip.empty[href="'.self::GUESS_URL.'"]');
        self::assertCount(1, $promptLink);
        self::assertStringContainsString('+ Zadat tip', $promptLink->text());

        // Item 11 replaced the row's „Tipovat →" action with the fixture itself:
        // the teams block is the link to the match, and on competition detail that
        // is the same guessing surface.
        self::assertGreaterThan(0, $crawler->filter('a.tip-row-match[href="'.self::GUESS_URL.'"]')->count());
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

    public function testMatchListLinksTheStatePillToTheMatchDetail(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/zapasy');
        self::assertResponseIsSuccessful();

        $pillLink = $crawler->filter('a.tip-row-pill-link[href="'.self::MATCH_DETAIL_URL.'"]');
        self::assertCount(1, $pillLink, '/zapasy must link the tippable row\'s pilulka');
        self::assertStringContainsString('Chybí tip', $pillLink->text());
    }

    /**
     * Item 18 — the Nástěnka card is ONE link instead: the pilulka and the „můj tip"
     * bar stopped being links (B7 linked them because the row was not a link; here it
     * is), the tip CTA is a *styled* element, and nothing interactive may be nested
     * inside `a.tip-row-link`. The „Rozložení tipů" strip IS a control, so it stays a
     * sibling of the link, never a descendant.
     */
    public function testDashboardCardIsOneLinkWithNothingInteractiveInside(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();

        $cards = $crawler->filter('.tip-row.is-dash');
        self::assertGreaterThan(0, $cards->count());
        self::assertCount(0, $crawler->filter('.tip-row.is-dash a.tip-row-pill-link'));
        self::assertCount(0, $crawler->filter('.tip-row.is-dash a.my-tip'));

        $cards->each(function (Crawler $card): void {
            // Exactly one link for the card itself…
            self::assertCount(1, $card->filter('a.tip-row-link'));
            // …and no control of any kind inside it.
            self::assertCount(0, $card->filter('a.tip-row-link a, a.tip-row-link button, a.tip-row-link input, a.tip-row-link select'));
        });

        // The tippable card reaches the match, and its CTA reads „Zadat tip" once.
        $tippable = $crawler->filter('a.tip-row-link[href="'.self::MATCH_DETAIL_URL.'"]');
        self::assertCount(1, $tippable);
        self::assertCount(1, $tippable->filter('.tipd-cta'));
        self::assertStringContainsString('Zadat tip', $tippable->filter('.tipd-cta')->text());
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

            self::assertCount(0, $crawler->filter('a.tip-row-pill-link'), $path.' must not link a locked row');
            self::assertCount(0, $crawler->filter('a.my-tip'), $path.' must not link a locked tip box');
        }

        // The Nástěnka card still links to the match (item 18: it is ONE link), but a
        // locked card must not invite a tip any more.
        $crawler = $client->request('GET', '/nastenka');
        self::assertGreaterThan(0, $crawler->filter('a.tip-row-link')->count());
        self::assertCount(0, $crawler->filter('.tipd-cta'), 'a locked Nástěnka card must not offer „Zadat tip"');
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
