<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\PurchaseBoost\PurchaseBoostCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Enum\BoostType;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;

/**
 * „Rozložení tipů" must be present on EVERY surface that lists matches — locked
 * with a one-click buy when the viewer has no entitlement, the real 1 / X / 2 bar
 * once they do. Regression cover for the 2026-07-23 report that the placeholder
 * existed only on the competition-scoped match page.
 */
final class TipStatsSurfacesTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string MATCHES_LIST = '/zapasy';
    /** The Nástěnka is soutěž-scoped since item 06 — point it at the boosts soutěž. */
    private const string DASHBOARD = '/nastenka?soutez='.AppFixtures::BOOSTS_COMPETITION_ID;
    private const string BOOSTS_DETAIL = '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID;
    private const string BOOSTS_PURCHASE = self::BOOSTS_DETAIL.'/vylepseni/koupit';
    private const string MATCH_DETAIL = '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID;
    private const string COMPETITION_MATCH = self::BOOSTS_DETAIL.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID;
    /**
     * The unlocked split, in both of its shapes: `.dist-bar` is the compact strip under a
     * match row, `.dist-fill` a labelled bar of the full card (item 10). The paywall's
     * blurred decoration is deliberately `.dist-ghost-fill`, so it never matches here.
     */
    private const string VISIBLE_BAR = '.dist-bar, .dist-fill';

    /**
     * A member of the boosts competition who has tipped the scheduled match and
     * owns no boost — the state every locked surface should render.
     */
    private function joinAndTip(string $userId): void
    {
        $this->testCommandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString($userId),
            token: AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ));
        $this->testCommandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString($userId),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));
    }

    private function grant(string $userId, int $amount): void
    {
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString($userId),
            amount: $amount,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    private function lockedStrips(Crawler $crawler): int
    {
        return $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="tip_distribution"]')->count();
    }

    private function visit(KernelBrowser $client, string $path): Crawler
    {
        $crawler = $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    public function testLockedPlaceholderWithBuyFormOnEveryMatchSurface(): void
    {
        $client = static::createClient();
        $this->joinAndTip(AppFixtures::VERIFIED_USER_ID);
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        foreach ([self::MATCHES_LIST, self::DASHBOARD, self::BOOSTS_DETAIL, self::MATCH_DETAIL, self::COMPETITION_MATCH] as $path) {
            $crawler = $this->visit($client, $path);

            self::assertGreaterThanOrEqual(
                1,
                $this->lockedStrips($crawler),
                sprintf('Expected a locked tip-distribution placeholder with a buy form on %s.', $path),
            );
            self::assertStringContainsString('Rozložení tipů', (string) $client->getResponse()->getContent());
        }
    }

    public function testBuyingTheBoostReplacesThePlaceholderWithTheBar(): void
    {
        $client = static::createClient();
        $this->joinAndTip(AppFixtures::VERIFIED_USER_ID);
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);
        $this->testCommandBus()->dispatch(new PurchaseBoostCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            type: BoostType::TipDistribution,
        ));
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        foreach ([self::MATCHES_LIST, self::BOOSTS_DETAIL, self::MATCH_DETAIL, self::COMPETITION_MATCH] as $path) {
            $crawler = $this->visit($client, $path);

            self::assertSame(0, $this->lockedStrips($crawler), sprintf('The paywall must be gone on %s.', $path));
            self::assertGreaterThanOrEqual(1, $crawler->filter(self::VISIBLE_BAR)->count(), sprintf('Expected the 1/X/2 bar on %s.', $path));
        }
    }

    public function testBuyingFromAListPlaceholderReturnsToThatList(): void
    {
        $client = static::createClient();
        $this->joinAndTip(AppFixtures::VERIFIED_USER_ID);
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $this->visit($client, self::MATCHES_LIST);
        $form = $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="tip_distribution"]')
            ->ancestors()->filter('form')->form();
        $client->submit($form);

        // The paywall lives outside the soutěž tree too — the redirect must come back here.
        self::assertResponseRedirects(self::MATCHES_LIST);
        $crawler = $client->followRedirect();
        self::assertGreaterThanOrEqual(1, $crawler->filter(self::VISIBLE_BAR)->count());
    }

    /**
     * Item 11 — a match and its „Rozložení tipů" are ONE card: every strip sits
     * inside the card's `.tip-row-extra` region, never as a card of its own next
     * to the row, and no card is left with an empty divider region.
     */
    public function testTheStripLivesInsideTheMatchCardOnEveryListSurface(): void
    {
        $client = static::createClient();
        $this->joinAndTip(AppFixtures::VERIFIED_USER_ID);
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        foreach ([self::MATCHES_LIST, self::DASHBOARD, self::BOOSTS_DETAIL] as $path) {
            $crawler = $this->visit($client, $path);

            $strips = $crawler->filter('.tip-stats-locked, .tip-stats-open');
            self::assertGreaterThan(0, $strips->count(), sprintf('Expected a tip-stats strip on %s.', $path));
            self::assertCount(
                $strips->count(),
                $crawler->filter('.tip-row-extra .tip-stats-locked, .tip-row-extra .tip-stats-open'),
                sprintf('A tip-stats strip rendered OUTSIDE a match card on %s.', $path),
            );

            $crawler->filter('.tip-row-extra')->each(function (Crawler $node) use ($path): void {
                self::assertNotSame('', trim($node->text()), sprintf('Empty .tip-row-extra on %s.', $path));
            });
        }
    }

    public function testOrganizerSeesTheSamePaywallAsMembers(): void
    {
        // ADMIN owns the boosts competition; owning it grants no free visibility.
        $client = static::createClient();
        $this->testCommandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 1,
        ));
        $this->grant(AppFixtures::ADMIN_ID, 100);
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $this->visit($client, self::COMPETITION_MATCH);

        self::assertGreaterThanOrEqual(1, $this->lockedStrips($crawler));
        self::assertCount(0, $crawler->filter(self::VISIBLE_BAR));
    }
}
