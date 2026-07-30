<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\PurchaseBoost\PurchaseBoostCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\BoostPurchase;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Enum\BoostType;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Item 35 — „Počkejte si na sestavy" (`BoostType::TipChange`) is sold where its
 * absence bites: the locked tip form of a zápas and `/souteze/{id}/moje-tipy`.
 * Until this item it was buyable ONLY from the „Získej výhody" card on competition
 * detail, which item 26 wanted deleted — deleting it without this would have
 * silently withdrawn a product `/cenik` still advertises.
 *
 * Two things are pinned hardest here, because both are easy to get subtly wrong:
 *
 * 1. the paywall names the CONCRETE moment the buyer gets back, resolved by
 *    {@see \App\Service\EffectiveTipDeadlineResolver} itself (through a second
 *    instance wired with `TipChangeGrantedEntitlements`), not by re-deriving
 *    „výkop mínus offset";
 * 2. that window is the competition's own `tipChangeOffsetMinutes` — „1 hodinu"
 *    is only the DEFAULT, never a constant.
 *
 * Fixture note: the boosts soutěž is created AFTER its zdroj's first kickoff, so
 * every one of its zápasy counts as late-added and is tippable until its own výkop
 * anyway — the booster would extend nothing, and is therefore correctly not
 * offered (see the last test). {@see lockTips} produces the ordinary situation in
 * which it does buy something.
 */
final class TipChangeShopTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string NOW = '2025-06-15 12:00:00 UTC';

    private const string BOOSTS_DETAIL = '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID;
    private const string BOOSTS_BATCH = self::BOOSTS_DETAIL.'/moje-tipy';
    private const string BOOSTS_MATCH = self::BOOSTS_DETAIL.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID;
    private const string BOOSTS_PURCHASE = self::BOOSTS_DETAIL.'/vylepseni/koupit';

    private const string PREMIUM_BATCH = '/souteze/'.AppFixtures::PREMIUM_COMPETITION_ID.'/moje-tipy';

    private const string TIP_CHANGE_FORM = 'form[action="'.self::BOOSTS_PURCHASE.'"] input[value="tip_change"]';

    /**
     * MATCH_SCHEDULED kicks off 2025-06-20 18:00 UTC = 20:00 Prague, so the default
     * 60-minute window hands the buyer back 19:00 Prague.
     */
    private const string REGAINED_DEADLINE = '20. 6. 2025 19:00';

    /**
     * „Uzamknout tipy · Ihned" — the ordinary way a soutěž's tips close while its
     * zápasy are still ahead, and therefore the state in which „Měnit tip" is worth
     * buying. Without it the fixture soutěž's zápasy are late-added and the booster
     * would extend nothing.
     */
    private function lockTips(): void
    {
        $em = $this->testEntityManager();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);

        $competition->lockTips(new \DateTimeImmutable(self::NOW));
        $competition->popEvents();

        $em->flush();
        $em->clear();
    }

    private function setTipChangeOffset(int $minutes): void
    {
        $em = $this->testEntityManager();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);

        $competition->changeTipChangeOffset($minutes, new \DateTimeImmutable(self::NOW));

        $em->flush();
        $em->clear();
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

    /** A member with money, on a soutěž whose tipy are already uzamčené. */
    private function lockedMemberWithCredits(): KernelBrowser
    {
        $client = static::createClient();
        $this->lockTips();
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        return $client;
    }

    // ── 1. The offer, and the moment it names ────────────────────────────────

    public function testTheBatchPageSellsItAndNamesTheZapasAndMomentItGivesBack(): void
    {
        $client = $this->lockedMemberWithCredits();

        $crawler = $client->request('GET', self::BOOSTS_BATCH);
        self::assertResponseIsSuccessful();

        // The page is in its „nothing left to tip" state — which is exactly what the
        // booster undoes.
        self::assertSelectorTextContains('body', 'Tipování uzavřeno');

        self::assertCount(1, $crawler->filter(self::TIP_CHANGE_FORM));
        self::assertSelectorTextContains('body', BoostType::TipChange->label());

        // Competition-wide surface ⇒ the panel names WHICH zápas it would hand back,
        // or the moment would be ambiguous. MATCH_SCHEDULED is the soonest one.
        self::assertSelectorTextContains('body', 'Sparta Praha – Slavia Praha');
        self::assertSelectorTextContains('body', 'až do '.self::REGAINED_DEADLINE);
    }

    public function testTheLockedTipFormOnTheMatchPageSellsItToo(): void
    {
        $client = $this->lockedMemberWithCredits();

        $crawler = $client->request('GET', self::BOOSTS_MATCH);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('.guess-locked', 'Tipování uzavřeno');
        self::assertCount(1, $crawler->filter('.guess-locked '.self::TIP_CHANGE_FORM));
        self::assertSelectorTextContains('.guess-locked', 'až do '.self::REGAINED_DEADLINE);

        // The bare shape brings no heading, so the sentence itself has to carry the
        // booster's ONE name (item 23) — otherwise „S tímhle vylepšením" names nothing
        // the player could recognise on /cenik or in the ledger.
        self::assertSelectorTextContains('.guess-locked', BoostType::TipChange->label());

        // The zápas is not named here: the page is already about it.
        self::assertSelectorTextNotContains('.guess-locked', 'Sparta Praha – Slavia Praha');
    }

    /**
     * Criterion 2 — the window is `Competition::$tipChangeOffsetMinutes`, and 60 is
     * merely its default. A hard-coded „1 hodinu" would be a lie here, and so would
     * a moment computed from it.
     */
    public function testACompetitionWithANonDefaultWindowShowsItsOwnNotOneHour(): void
    {
        $client = static::createClient();
        $this->lockTips();
        $this->setTipChangeOffset(120);
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', self::BOOSTS_BATCH);
        self::assertResponseIsSuccessful();

        // Two hours before the 20:00 Prague výkop, not one.
        self::assertSelectorTextContains('body', 'až do 20. 6. 2025 18:00');
        self::assertSelectorTextContains('body', 'až 2 h před začátkem zápasu');
        self::assertSelectorTextNotContains('body', 'až 1 hodinu před začátkem zápasu');
    }

    // ── 2. Where it must NOT be offered ──────────────────────────────────────

    public function testAViewerWhoAlreadyOwnsItIsOfferedNothing(): void
    {
        $client = $this->lockedMemberWithCredits();

        $this->testCommandBus()->dispatch(new PurchaseBoostCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            type: BoostType::TipChange,
        ));

        $crawler = $client->request('GET', self::BOOSTS_BATCH);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter(self::TIP_CHANGE_FORM));
    }

    public function testAPremiumCompetitionSellsNothingBecauseTheOrganizerDecides(): void
    {
        // Premium XOR boosts: the manager's toggle grants „Měnit tip" for everyone,
        // so a per-player purchase must not even be advertised.
        $client = static::createClient();
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::PREMIUM_BATCH);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action*="/vylepseni/koupit"]'));
    }

    public function testAFullyOverCompetitionSellsNothing(): void
    {
        // B6 — a booster bought now could no longer unlock anything.
        $client = static::createClient();
        $this->lockTips();
        $this->finishEveryMatch();
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_BATCH);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter(self::TIP_CHANGE_FORM));
    }

    /**
     * The „only where it can do something" rule, and the reason the fixture soutěž
     * shows no offer until {@see lockTips} runs: its zápasy are late-added, so their
     * uzávěrka already IS their own výkop and the booster has nothing to extend.
     * Selling it there would take 50 kr. for no change whatsoever.
     */
    public function testAZapasTheBoosterCouldNotExtendIsNotSoldAsIfItCould(): void
    {
        $client = static::createClient();
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_BATCH);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter(self::TIP_CHANGE_FORM));
    }

    // ── 3. End to end ────────────────────────────────────────────────────────

    public function testBuyingItFromTheBatchPageReopensTheTable(): void
    {
        $client = $this->lockedMemberWithCredits();

        $crawler = $client->request('GET', self::BOOSTS_BATCH);
        $form = $crawler->filter(self::TIP_CHANGE_FORM)->ancestors()->filter('form')->form();
        $client->submit($form);

        self::assertResponseRedirects(self::BOOSTS_BATCH);
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('body', 'je aktivní');

        // The whole point: the tip inputs are back, and the offer is gone.
        self::assertGreaterThanOrEqual(1, $crawler->filter('input[name^="guesses["]')->count());
        self::assertCount(0, $crawler->filter(self::TIP_CHANGE_FORM));

        $em = $this->testEntityManager();
        $em->clear();
        $purchase = $em->createQueryBuilder()
            ->select('b')
            ->from(BoostPurchase::class, 'b')
            ->where('b.user = :user')
            ->andWhere('b.competition = :competition')
            ->andWhere('b.type = :type')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('user', Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID))
            ->setParameter('competition', Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID))
            ->setParameter('type', BoostType::TipChange)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(BoostPurchase::class, $purchase);
    }

    private function finishEveryMatch(): void
    {
        $em = $this->testEntityManager();
        $now = new \DateTimeImmutable(self::NOW);

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
