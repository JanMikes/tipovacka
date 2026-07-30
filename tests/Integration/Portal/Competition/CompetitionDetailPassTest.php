<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\MarkBoostIntroSeen\MarkBoostIntroSeenCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Enum\BoostType;
use App\Service\Credits\PricingConfig;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;

/**
 * Item 19 — the competition-detail pass: the popis, the „Pozvat kamaráda" CTA, the
 * one-column order, five matches with a JS-free load-more (B25) and the first-visit
 * boost-price modal (which closes B10).
 *
 * Deliberately NOT asserted here: who may see whose tips. That rule lives in
 * `TipVisibilityGate` and is being changed to „has a final result"; the assertions
 * below only touch states that are stable under either rule.
 */
final class CompetitionDetailPassTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string OWN_DETAIL = '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID;
    private const string BOOSTS_DETAIL = '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID;
    private const string PREMIUM_DETAIL = '/souteze/'.AppFixtures::PREMIUM_COMPETITION_ID;
    private const string BOOSTS_DISMISS = self::BOOSTS_DETAIL.'/vylepseni/uvod/skryt';

    // ── A. the order, in one column ─────────────────────────────────────────

    public function testTheSectionsRenderInTheOrderTheProductOwnerPicked(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);
        $this->setDescription('Popis pro pořadí.');

        $body = $this->body($client, self::OWN_DETAIL);

        $positions = [
            'popis' => mb_strpos($body, 'Popis pro pořadí.'),
            'pozvat' => mb_strpos($body, 'Pozvat kamaráda'),
            'banner' => mb_strpos($body, 'Tipněte si všechny zápasy najednou'),
            'tabulka' => mb_strpos($body, 'Tabulka soutěže'),
            'zebricek' => mb_strpos($body, 'id="zebricek"'),
            'vylepseni' => mb_strpos($body, 'id="vylepseni"'),
        ];

        foreach ($positions as $name => $at) {
            self::assertIsInt($at, sprintf('„%s" must be on the page.', $name));
        }

        $order = array_values($positions);
        $sorted = $order;
        sort($sorted);
        self::assertSame($sorted, $order, 'popis → Pozvat kamaráda → banner → Tabulka soutěže → Žebříček → Prémiové funkce.');

        // The aside is gone: one column, no lg:col-span-8/4 grid any more.
        self::assertStringNotContainsString('lg:col-span-8', $body);
        self::assertStringNotContainsString('<aside', $body);
    }

    public function testZebricekAndBoostPanelKeepWhatItem08GaveThem(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        // Real žebříček rows + „Celý žebříček" → /zebricek?soutez=…, per-row member links.
        self::assertGreaterThanOrEqual(1, $crawler->filter('#zebricek .lb-row')->count());
        self::assertCount(1, $crawler->filter('#zebricek a[href="/zebricek?soutez='.AppFixtures::BOOSTS_COMPETITION_ID.'"]'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('#zebricek a[href^="/zebricek/clen/"]')->count());

        // The boost panel, in the single column now.
        self::assertGreaterThanOrEqual(1, $crawler->filter('#vylepseni')->count());
        self::assertSelectorTextContains('#vylepseni', 'Získej výhody');
    }

    // ── B. popis soutěže ────────────────────────────────────────────────────

    public function testAnEmptyDescriptionRendersNothingAtAll(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $body = $this->body($client, self::OWN_DETAIL);

        // No placeholder prose, no empty box: the paragraph simply is not there.
        self::assertStringNotContainsString('whitespace-pre-line', $body);
    }

    public function testDescriptionIsEscaped(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);
        $this->setDescription('<script>alert("xss")</script> "uvozovky" & <b>bold</b>');

        $body = $this->body($client, self::OWN_DETAIL);

        // Rendered, but as TEXT — never as markup.
        self::assertStringNotContainsString('<script>alert', $body);
        self::assertStringNotContainsString('<b>bold</b>', $body);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $body);
        self::assertStringContainsString('&quot;uvozovky&quot; &amp; &lt;b&gt;bold&lt;/b&gt;', $body);
    }

    public function testDescriptionIsEditableOnTheEditFormAndShowsUpOnTheDetailPage(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::OWN_DETAIL.'/upravit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit změny', [
            'competition_form[description]' => 'Hrajeme o čest a nic víc.',
        ]);
        self::assertResponseRedirects();

        self::assertStringContainsString('Hrajeme o čest a nic víc.', $this->body($client, self::OWN_DETAIL));
    }

    public function testDescriptionIsCappedAtTheOneDomainConstant(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::OWN_DETAIL.'/upravit');
        $client->submitForm('Uložit změny', [
            'competition_form[description]' => str_repeat('a', Competition::DESCRIPTION_MAX_LENGTH + 1),
        ]);

        // Rejected, not saved — the form comes back with the violation.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Popis soutěže nesmí být delší než '.Competition::DESCRIPTION_MAX_LENGTH.' znaků.');
    }

    // ── C. five matches, and B25 ────────────────────────────────────────────

    public function testEveryMatchIsRenderedVisibleAndTheLoadMoreButtonStartsHidden(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        $items = $crawler->filter('#zapasy li[data-reveal-target="item"]');
        self::assertGreaterThan(0, $items->count());

        // B25: NOTHING is pre-hidden server-side — collapsing is the enhanced state,
        // so with JavaScript off every match is reachable.
        $items->each(function (Crawler $item): void {
            self::assertStringNotContainsString('hidden', (string) $item->attr('class'), 'A match row must never be hidden server-side.');
        });

        $toggle = $crawler->filter('#zapasy button[data-reveal-target="toggle"]');

        if ($toggle->count() > 0) {
            // …and the button that only works with JS is hidden until JS unhides it.
            self::assertNotNull($toggle->attr('hidden'));
            self::assertStringContainsString('Načíst všechny zápasy', $toggle->text());
        }
    }

    public function testTheLoadMoreButtonAppearsOnlyWhenThereAreMoreThanFiveMatches(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::OWN_DETAIL);
        self::assertResponseIsSuccessful();

        $items = $crawler->filter('#zapasy li[data-reveal-target="item"]')->count();
        $toggle = $crawler->filter('#zapasy button[data-reveal-target="toggle"]')->count();

        self::assertSame($items > 5 ? 1 : 0, $toggle);
    }

    // ── D. the match card is clickable as a whole ───────────────────────────

    public function testEachMatchCardIsOneLinkWithNothingInteractiveInsideIt(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        $cardCount = $crawler->filter('#zapasy .tip-row')->count();
        self::assertGreaterThan(0, $cardCount);
        self::assertCount($cardCount, $crawler->filter('#zapasy .tip-row.is-dash'), 'Competition detail renders the ONE card design.');
        self::assertCount($cardCount, $crawler->filter('#zapasy .tip-row > a.tip-row-link'), 'Every card is exactly one link.');

        // Nothing interactive may be nested inside that link (B7's rule, kept).
        self::assertCount(0, $crawler->filter('a.tip-row-link a'));
        self::assertCount(0, $crawler->filter('a.tip-row-link button'));
        self::assertCount(0, $crawler->filter('a.tip-row-link input'));

        // The card links INTO this competition's match page, not the bare /zapasy/{id}.
        self::assertGreaterThanOrEqual(1, $crawler->filter('#zapasy a.tip-row-link[href^="'.self::BOOSTS_DETAIL.'/zapasy/"]')->count());
    }

    public function testALockedUntippedMatchStillSaysNetipovanoOnThisPage(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);
        $this->lockEveryTip();

        $crawler = $client->request('GET', self::OWN_DETAIL);
        self::assertResponseIsSuccessful();

        // B5: „Netipováno" is competition-detail only, and it has to survive the card
        // design — the footer of a locked, untipped card renders it.
        self::assertGreaterThanOrEqual(1, $crawler->filter('#zapasy .my-tip.none')->count());
        self::assertSelectorTextContains('#zapasy', 'Netipováno');
    }

    public function testTheUzaverkaFootNoteStillRendersInsideTheCard(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::OWN_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertGreaterThanOrEqual(1, $crawler->filter('#zapasy .tip-row-note')->count());
        self::assertSelectorTextContains('#zapasy .tip-row-note', 'Uzávěrka');
    }

    // ── E. the first-visit boost-price modal ────────────────────────────────

    public function testMemberOfABoostsCompetitionGetsThePriceModalOnTheFirstVisit(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('dialog[data-boost-intro-target="dialog"]'));
        self::assertSelectorTextContains('dialog', 'Co si můžete v téhle soutěži odemknout');

        // Every price comes from PricingConfig via BoostType — no literals anywhere.
        $dialog = $crawler->filter('dialog')->text();

        foreach (BoostType::cases() as $type) {
            self::assertStringContainsString($type->label(), $dialog);
            self::assertStringContainsString($type->price().' kr.', $dialog);
        }

        self::assertStringContainsString(PricingConfig::BOOST_TIP_DISTRIBUTION.' kr.', $dialog);

        // Three dismissals, ONE persistence path.
        self::assertCount(1, $crawler->filter('dialog form[action="'.self::BOOSTS_DISMISS.'"]'));
        self::assertCount(1, $crawler->filter('dialog button[aria-label="Zavřít"]'));
        self::assertSelectorTextContains('dialog', 'Pochopil jsem, již nezobrazovat');
    }

    public function testDismissingItStampsTheMembershipAndItNeverComesBack(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        $token = $crawler->filter('dialog form input[name="_token"]')->attr('value');
        self::assertIsString($token);

        $client->request('POST', self::BOOSTS_DISMISS, ['_token' => $token]);
        self::assertResponseRedirects(self::BOOSTS_DETAIL);

        self::assertNotNull($this->membership()->boostIntroSeenAt);

        // A FRESH SESSION: throw away every cookie (session id included) and log in
        // again. The dismissal lives in the database, not in the session or in
        // localStorage, so it holds. (Also walked in a real browser with a separate
        // browser context, for all three dismissals.)
        $client->getCookieJar()->clear();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $again = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $again->filter('dialog[data-boost-intro-target="dialog"]'));
    }

    public function testDismissingTwiceKeepsTheFirstMomentAndDoesNotError(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        $token = (string) $crawler->filter('dialog form input[name="_token"]')->attr('value');

        $client->request('POST', self::BOOSTS_DISMISS, ['_token' => $token]);
        $first = $this->membership()->boostIntroSeenAt;
        self::assertNotNull($first);

        $client->request('POST', self::BOOSTS_DISMISS, ['_token' => $token]);
        self::assertResponseRedirects(self::BOOSTS_DETAIL);
        self::assertEquals($first, $this->membership()->boostIntroSeenAt);
    }

    public function testDismissalWithoutACsrfTokenIsRefused(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('POST', self::BOOSTS_DISMISS, ['_token' => 'nonsense']);
        self::assertResponseStatusCodeSame(400);
        self::assertNull($this->membership()->boostIntroSeenAt);
    }

    public function testThePriceModalNeverAppearsOnAPremiumCompetition(): void
    {
        // Premium XOR boosts: the organizer pays for everyone and a player CANNOT buy
        // a boost here at all, so a price list would advertise the unpurchasable.
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::PREMIUM_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertCount(0, $crawler->filter('dialog[data-boost-intro-target="dialog"]'));
        self::assertSelectorTextContains('#vylepseni', 'Vylepšení máte v ceně');
    }

    public function testThePriceModalNeverAppearsOnAFullyOverCompetition(): void
    {
        // B6: a boost bought now could no longer unlock anything.
        $client = static::createClient();
        $this->finishEveryMatchOfTheBoostsCompetition();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertCount(0, $crawler->filter('dialog[data-boost-intro-target="dialog"]'));
        self::assertSelectorTextContains('body', 'Soutěž už skončila');
    }

    public function testThePriceModalNeverAppearsForANonMember(): void
    {
        // Only an admin ever reaches a competition they are not a member of.
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('dialog[data-boost-intro-target="dialog"]'));
    }

    public function testDismissalByANonMemberIsASilentNoOpRatherThanAnError(): void
    {
        // The admin is not a member of VERIFIED_COMPETITION but may view it, so the
        // route is reachable for them. There is no membership to stamp: the handler
        // must shrug rather than blow up (or invent one).
        static::createClient();

        $before = $this->membershipCount(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->testCommandBus()->dispatch(new MarkBoostIntroSeenCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
        ));

        self::assertSame($before, $this->membershipCount(AppFixtures::VERIFIED_COMPETITION_ID), 'No membership is invented for a non-member.');
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function body(KernelBrowser $client, string $url): string
    {
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return (string) $client->getResponse()->getContent();
    }

    private function membership(): Membership
    {
        $em = $this->testEntityManager();
        $em->clear();

        $membership = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.competition = :competition')
            ->andWhere('m.user = :user')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('competition', Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID))
            ->setParameter('user', Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID))
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Membership::class, $membership);

        return $membership;
    }

    private function membershipCount(string $competitionId): int
    {
        $em = $this->testEntityManager();
        $em->clear();

        $count = $em->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Membership::class, 'm')
            ->where('m.competition = :competition')
            ->setParameter('competition', Uuid::fromString($competitionId))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    private function setDescription(string $description): void
    {
        $em = $this->testEntityManager();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        $competition->updateDetails(
            name: $competition->name,
            description: $description,
            hideOthersTipsBeforeDeadline: $competition->hideOthersTipsBeforeDeadline,
            now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $competition->popEvents();
        $em->flush();
        $em->clear();
    }

    private function lockEveryTip(): void
    {
        $em = $this->testEntityManager();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        $competition->lockTips(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $competition->popEvents();
        $em->flush();
        $em->clear();
    }

    private function finishEveryMatchOfTheBoostsCompetition(): void
    {
        $em = $this->testEntityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        foreach ([AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_LIVE_ID, AppFixtures::MATCH_PLAYOFF_ID] as $matchId) {
            $match = $em->find(SportMatch::class, Uuid::fromString($matchId));
            self::assertInstanceOf(SportMatch::class, $match);
            $match->setFinalScore(1, 0, null, null, null, $now);
            $match->popEvents();
        }

        $em->flush();
        $em->clear();
    }
}
