<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\DataFixtures\AppFixtures;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Item 22 — `/zapasy/{id}` is the SOURCE-side match page: fixture, „Průběh zápasu"
 * and „Správa zápasu", for admin OR the match source's owner (`sport_match_manage`).
 *
 * The gate is the part that would silently rot, so it is pinned from all three
 * sides: a plain member 403s, a NON-admin source owner does not, an admin does not.
 * „Gate it by ROLE_ADMIN" was the reported hypothesis and would have locked out
 * every organizer of a from-scratch soutěž — a `private` zdroj zápasů belongs to an
 * ordinary user, and this page is where their own management actions land.
 *
 * Everything player-facing moved to `/souteze/{cid}/zapasy/{mid}`; see
 * tests/Integration/Portal/Competition/CompetitionMatchDetailFlowTest.
 */
final class MatchDetailPageTest extends WebTestCase
{
    use WebFlowHelpers;

    /** On PUBLIC_SOURCE, whose owner is ADMIN. */
    private const string CURATED_MATCH = '/zapasy/'.AppFixtures::MATCH_FINISHED_ID;
    /** On PRIVATE_SOURCE, whose owner is VERIFIED_USER — an ordinary, non-admin user. */
    private const string PRIVATE_MATCH = '/zapasy/'.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;

    public function testPlainMemberIsForbidden(): void
    {
        $client = static::createClient();
        // SECOND_VERIFIED_USER is a member of two soutěže on PUBLIC_SOURCE and owns
        // no zdroj zápasů at all — exactly the player this route is no longer for.
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', self::CURATED_MATCH);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNonAdminSourceOwnerReachesTheirOwnSourcesMatch(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::PRIVATE_MATCH);

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('h2', 'Správa zápasu');
    }

    /** …and only their own: a foreign zdroj's match is still refused. */
    public function testNonAdminSourceOwnerIsForbiddenOnAnotherSourcesMatch(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::CURATED_MATCH);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminReachesAnyMatch(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('GET', self::CURATED_MATCH);
        self::assertResponseIsSuccessful();

        $client->request('GET', self::PRIVATE_MATCH);
        self::assertResponseIsSuccessful();
    }

    public function testAnonymousVisitorIsSentToTheLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', self::CURATED_MATCH);

        self::assertResponseRedirects();
        self::assertStringContainsString('/prihlaseni', (string) $client->getResponse()->headers->get('Location'));
    }

    /**
     * The page carries the fixture, the timeline and the management block — and
     * nothing player-facing. Those sections are scoped to ONE soutěž, which this
     * route does not carry; leaving them here is what made the two pages
     * near-duplicates in the first place.
     */
    public function testTheSourceSidePageCarriesNoPlayerSurfaces(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', self::CURATED_MATCH);
        self::assertResponseIsSuccessful();

        self::assertAnySelectorTextContains('h2', 'Průběh zápasu');
        self::assertAnySelectorTextContains('h2', 'Správa zápasu');

        self::assertCount(0, $crawler->filter('#soutez-switcher-match'));
        self::assertCount(0, $crawler->filter('.tip-inputs'));
        self::assertCount(0, $crawler->filter('.tip-stats-open, .tip-stats-locked, .dist-card'));
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Váš tip', $body);
        self::assertStringNotContainsString('Pořadí za zápas', $body);
        self::assertStringNotContainsString('Proč tu nejsou všechny vaše soutěže', $body);
    }
}
