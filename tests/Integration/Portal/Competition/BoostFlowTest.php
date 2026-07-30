<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\BoostPurchase;
use App\Entity\CreditWallet;
use App\Entity\SportMatch;
use App\Enum\BoostType;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Portal boost commerce: the „Získej výhody" sidebar, buying from the paywall,
 * the premium pill on premium competitions, and the insufficient-credits top-up
 * link. See .docs/DOMAIN.md §Monetization.
 */
final class BoostFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string BOOSTS_DETAIL = '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID;
    private const string BOOSTS_PURCHASE = self::BOOSTS_DETAIL.'/vylepseni/koupit';
    private const string PREMIUM_MATCH = '/souteze/'.AppFixtures::PREMIUM_COMPETITION_ID.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID;
    /** Match detail scoped to the boosts soutěž — the page carrying BOTH full paywall cards (B27). */
    private const string BOOSTS_MATCH_DETAIL = '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'?soutez='.AppFixtures::BOOSTS_COMPETITION_ID;

    private function grant(string $userId, int $amount): void
    {
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString($userId),
            amount: $amount,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    private function balanceOf(string $userId): int
    {
        $em = $this->testEntityManager();
        $em->clear();

        return (int) $em->createQueryBuilder()
            ->select('w.balance')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :user')
            ->setParameter('user', Uuid::fromString($userId))
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function activeTipChange(string $userId): ?BoostPurchase
    {
        $em = $this->testEntityManager();
        $em->clear();

        return $em->createQueryBuilder()
            ->select('b')
            ->from(BoostPurchase::class, 'b')
            ->where('b.user = :user')
            ->andWhere('b.competition = :competition')
            ->andWhere('b.type = :type')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('user', Uuid::fromString($userId))
            ->setParameter('competition', Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID))
            ->setParameter('type', BoostType::TipChange)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function testMemberSeesBoostSidebarWithOwnedAndBuyStates(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body', 'Získej výhody');
        // SECOND_VERIFIED_USER owns OthersTips (fixture) → shown as active, not buyable.
        // The panel row carries the marketing HEADLINE (ROUND2 decision 2); the canonical
        // BoostType::label() name lives in the confirm dialog / ledger / cenik.
        self::assertSelectorTextContains('body', 'Přesné tipy soupeřů');

        // The tip_change boost is buyable (affordable) → a purchase form is present.
        $forms = $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"]');
        self::assertGreaterThanOrEqual(1, $forms->count());
        self::assertCount(1, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="tip_change"]'));
    }

    public function testManagerSidebarOffersEveryBoostLikeAnyOtherPlayer(): void
    {
        // ADMIN owns BOOSTS_COMPETITION but gets no free visibility (2026-07-23):
        // the organizer plays too, so all three boosts are offered to them as well.
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);
        $this->grant(AppFixtures::ADMIN_ID, 100);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        $sidebar = $crawler->filter('section form[action="'.self::BOOSTS_PURCHASE.'"]');
        self::assertGreaterThanOrEqual(1, $sidebar->filter('input[value="tip_distribution"]')->count());
        self::assertGreaterThanOrEqual(1, $sidebar->filter('input[value="others_tips"]')->count());
        self::assertGreaterThanOrEqual(1, $sidebar->filter('input[value="tip_change"]')->count());
    }

    public function testBuyBoostFromSidebarWritesRowAndRedirects(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        $form = $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="tip_change"]')->ancestors()->filter('form')->form();
        $client->submit($form);

        self::assertResponseRedirects(self::BOOSTS_DETAIL);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'je aktivní');

        self::assertInstanceOf(BoostPurchase::class, $this->activeTipChange(AppFixtures::SECOND_VERIFIED_USER_ID));
    }

    public function testDoubleBuyOfOwnedBoostShowsFriendlyMessageWithoutError(): void
    {
        // Double-click on „Koupit": SECOND_VERIFIED_USER already owns OthersTips
        // (fixture). A repeat purchase of it must never 500 — a friendly flash and
        // still exactly one active OthersTips row. The boost-purchase CSRF token is
        // shared per competition, so it is grabbed from the rendered tip_change form.
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        $token = $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[name="_token"]')->first()->attr('value');

        $client->request('POST', self::BOOSTS_PURCHASE, [
            '_token' => $token,
            'type' => 'others_tips',
            '_redirect' => self::BOOSTS_DETAIL,
        ]);

        self::assertResponseRedirects(self::BOOSTS_DETAIL);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'už v této soutěži máte');

        // No duplicate row, balance untouched (nothing charged).
        $em = $this->testEntityManager();
        $em->clear();
        $active = $em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(BoostPurchase::class, 'b')
            ->where('b.user = :user')
            ->andWhere('b.competition = :competition')
            ->andWhere('b.type = :type')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('user', Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID))
            ->setParameter('competition', Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID))
            ->setParameter('type', BoostType::OthersTips)
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(1, (int) $active);
    }

    public function testPremiumCompetitionGuessPageShowsPremiumPillNotBuyForm(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::PREMIUM_MATCH);
        self::assertResponseIsSuccessful();

        // Premium (toggles off) → the paywall becomes a „Prémium" note, never a boost buy form.
        self::assertSelectorTextContains('body', 'Prémium');
        self::assertCount(0, $crawler->filter('form[action*="/vylepseni/koupit"]'));
    }

    public function testNonEntitledMemberSeesInlinePaywallOnGuessPage(): void
    {
        // VERIFIED_USER joins the boosts competition (no boost) and views a
        // pre-deadline match — the distribution + others paywalls render with buy
        // CTAs (the inline Boost:Panel branch).
        $client = static::createClient();
        $this->testCommandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            token: AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ));
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $guessPage = self::BOOSTS_DETAIL.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID;
        $crawler = $client->request('GET', $guessPage);
        self::assertResponseIsSuccessful();

        // Locked distribution + others paywalls each offer a boost purchase form.
        self::assertGreaterThanOrEqual(1, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="tip_distribution"]')->count());
        self::assertGreaterThanOrEqual(1, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="others_tips"]')->count());
        self::assertSelectorTextContains('body', 'Odemknout');
    }

    /**
     * B27 — both full paywall cards of the match detail let a player buy by clicking
     * the WHOLE card, via a submit stretched over it. Three things are pinned here:
     *
     * 1. that submit ships `hidden`; the `confirm` Stimulus controller unhides it on
     *    connect, so a page whose JavaScript never ran (a throw, a failed asset
     *    fetch, B16's `disconnect()`) keeps only the small explicit button — the big
     *    target is the enhancement, the small one the floor;
     * 2. nothing interactive is nested inside it (items 18/21: ONE wrapping control,
     *    every other control a sibling);
     * 3. it lives in the SAME form as the small button, so both share one CSRF token,
     *    one price and one confirm dialog — they can never drift apart.
     */
    public function testWholeCardPaywallTargetShipsHiddenAndNestsNothingInteractive(): void
    {
        $client = static::createClient();
        $this->testCommandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            token: AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ));
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_MATCH_DETAIL);
        self::assertResponseIsSuccessful();

        $stretched = $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] button[data-confirm-target="stretch"]');
        self::assertCount(2, $stretched, 'Both full paywall cards („Rozložení tipů" + „Pořadí za zápas") must offer the whole-card target.');

        foreach ($stretched as $node) {
            self::assertTrue($node->hasAttribute('hidden'), 'The stretched submit must ship hidden — the confirm controller enables it.');
            self::assertSame('submit', $node->getAttribute('type'));
        }

        self::assertCount(
            0,
            $crawler->filter('button[data-confirm-target="stretch"] a, button[data-confirm-target="stretch"] button, button[data-confirm-target="stretch"] input, button[data-confirm-target="stretch"] [data-controller]'),
            'Nothing interactive may be nested inside the stretched control.',
        );

        // The explicit small CTA stays — and both controls sit in one confirm form.
        $forms = $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"]');
        self::assertCount(2, $forms);
        self::assertCount(2, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] button.dist-unlock'));

        foreach ($forms as $form) {
            self::assertSame('confirm', $form->getAttribute('data-controller'));
            self::assertNotSame('', $form->getAttribute('data-confirm-title-value'));
            self::assertNotSame('', $form->getAttribute('data-confirm-confirm-label-value'));
        }
    }

    /**
     * The dialog is UX, not a guard: a POST without a valid CSRF token must charge
     * nothing even though the enlarged click target makes an accidental submit more
     * likely. The stale-page and double-buy cases are pinned further down.
     */
    public function testPurchaseWithoutAValidCsrfTokenChargesNothing(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        $client->request('POST', self::BOOSTS_PURCHASE, [
            '_token' => 'not-a-token',
            'type' => 'tip_change',
            '_redirect' => self::BOOSTS_DETAIL,
        ]);

        self::assertResponseRedirects(self::BOOSTS_DETAIL);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Neplatný bezpečnostní token');

        self::assertNull($this->activeTipChange(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertSame(100, $this->balanceOf(AppFixtures::SECOND_VERIFIED_USER_ID));
    }

    public function testBrokeMemberSeesTopUpLinkInsteadOfBuyForm(): void
    {
        $client = static::createClient();
        // SECOND_VERIFIED_USER is a BOOSTS member with 0 balance and no boosts.
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        // Cannot afford anything ⇒ no purchase form, but a top-up link is offered.
        self::assertCount(0, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"]'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('a[href="/kredity"]')->count());
    }

    // ── B6: no purchase once the competition is fully over ────────────────────

    /**
     * Settle every match the boosts competition includes, so it becomes „fully
     * over" per {@see \App\Service\Competition\CompetitionMatchProvider::isFullyOver}
     * — nothing Scheduled, Live or Postponed left.
     */
    private function finishEveryMatch(): void
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

    public function testFinishedCompetitionOffersNoPurchaseCtaAndSaysWhy(): void
    {
        $client = static::createClient();
        $this->finishEveryMatch();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertCount(0, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"]'), 'A finished competition must not offer a boost purchase.');
        self::assertSelectorTextContains('body', 'Soutěž už skončila');
    }

    public function testBuyingInAFinishedCompetitionIsRefusedAndChargesNothing(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        // Grab a valid CSRF token while the competition is still running, then
        // settle every match — a stale page must not be able to burn credits.
        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        $token = $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[name="_token"]')->first()->attr('value');
        $this->finishEveryMatch();

        $client->request('POST', self::BOOSTS_PURCHASE, [
            '_token' => $token,
            'type' => 'tip_change',
            '_redirect' => self::BOOSTS_DETAIL,
        ]);

        self::assertResponseRedirects(self::BOOSTS_DETAIL);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Soutěž už skončila');

        self::assertNull($this->activeTipChange(AppFixtures::SECOND_VERIFIED_USER_ID));
    }
}
