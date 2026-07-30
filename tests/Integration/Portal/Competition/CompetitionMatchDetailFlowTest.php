<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Item 22 — `/souteze/{competitionId}/zapasy/{sportMatchId}` is THE match page.
 *
 * It carries what used to be split over two near-duplicate routes: the fixture and
 * the tip form as ONE card, the team form, B4's scope panel, „Rozložení tipů",
 * „Průběh zápasu", „Pořadí za zápas" and the organizer's blocks. The soutěž comes
 * from the PATH; `?soutez=` (all the switcher's GET form can append) redirects to
 * the chosen soutěž's own URL, and an id that is unknown, foreign or excludes the
 * match is ignored rather than refused.
 */
final class CompetitionMatchDetailFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private static function url(string $competitionId, string $sportMatchId): string
    {
        return '/souteze/'.$competitionId.'/zapasy/'.$sportMatchId;
    }

    // ─────────────────────────── access ───────────────────────────

    public function testMemberCanLoadTheMatchPage(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('GET', self::url(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID));

        self::assertResponseIsSuccessful();
    }

    public function testNonMemberIsDenied(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::url(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID));

        self::assertResponseStatusCodeSame(403);
    }

    public function testMatchOutsideSubsetSelectionIsConflict(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        // MATCH_PLAYOFF is not among the subset competition's selected matches.
        $client->request('GET', self::url(AppFixtures::SUBSET_COMPETITION_ID, AppFixtures::MATCH_PLAYOFF_ID));

        self::assertResponseStatusCodeSame(409);
    }

    public function testAnonymousVisitorIsSentToTheLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', self::url(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::MATCH_FINISHED_ID));

        self::assertResponseRedirects();
        self::assertStringContainsString('/prihlaseni', (string) $client->getResponse()->headers->get('Location'));
    }

    // ─────────────────────── the merged card (§5) ───────────────────────

    /**
     * The fixture and „Váš tip" are ONE card, and each team name is written exactly
     * once — the guess form's own labels stay for screen readers but go `sr-only`,
     * so the visible name above each spinner is the card's.
     */
    public function testTheFixtureAndTheTipFormAreOneCardWithEachTeamNamedOnce(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', self::url(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID));
        self::assertResponseIsSuccessful();

        $card = $crawler->filter('section.match-card');
        self::assertCount(1, $card, 'The fixture and the tip form must share ONE card frame.');
        self::assertCount(2, $card->filter('.mc-name'), 'One visible name per side.');
        self::assertCount(1, $card->filter('.tip-inputs'), 'The tip spinners live inside that same card.');
        self::assertAnySelectorTextContains('.mc-tip-head', 'Váš tip');

        // The team name appears exactly once as visible card text; the form's own
        // labels are still in the markup (accessible names) but sr-only.
        self::assertSame(1, substr_count($card->filter('.mc-name')->text(), 'Sparta Praha'));
        self::assertCount(2, $card->filter('.tip-inputs label.sr-only'));
    }

    /**
     * The case the merge puts at risk: on a finished match the real RESULT and the
     * viewer's TIP are both on the card. They must be labelled apart.
     */
    public function testFinishedMatchLabelsTheResultApartFromTheViewersTip(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', self::url(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::MATCH_FINISHED_ID));
        self::assertResponseIsSuccessful();

        $card = $crawler->filter('section.match-card');
        self::assertSame('Konečný výsledek', trim($card->filter('.mc-score-lbl')->text()));
        self::assertSame('2:1', preg_replace('/\s+/', '', $card->filter('.mc-score')->text()));

        // …and the viewer's own tip (ADMIN guessed 3:0) under its own „Váš tip" label.
        self::assertAnySelectorTextContains('.mc-tip-head', 'Váš tip');
        self::assertStringContainsString('3 : 0', $card->filter('.mc-tip')->text());
        // The points badge belongs with the tip, never with the result.
        self::assertCount(1, $card->filter('.mc-tip-head .my-tip-pts'));
    }

    // ──────────────────── switcher, scope and B4 ────────────────────

    /**
     * SECOND_VERIFIED_USER is in three soutěže on the curated zdroj: PREMIUM and
     * BOOSTS include MATCH_LIVE, SUBSET does not. The switcher offers exactly the
     * two that include it, and the B4 panel explains the third — the same soutěž is
     * never described by both.
     */
    public function testSwitcherOffersOnlyTheSoutezeThatIncludeTheMatch(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::url(AppFixtures::PREMIUM_COMPETITION_ID, AppFixtures::MATCH_LIVE_ID));
        self::assertResponseIsSuccessful();

        $optionValues = $crawler->filter('#soutez-switcher-match option')->each(
            static fn ($node): string => (string) $node->attr('value'),
        );

        self::assertContains(AppFixtures::PREMIUM_COMPETITION_ID, $optionValues);
        self::assertContains(AppFixtures::BOOSTS_COMPETITION_ID, $optionValues);
        self::assertNotContains(AppFixtures::SUBSET_COMPETITION_ID, $optionValues);

        self::assertAnySelectorTextContains('h3', 'Proč tu nejsou všechny vaše soutěže');
        self::assertAnySelectorTextContains('section', AppFixtures::SUBSET_COMPETITION_NAME);
    }

    /**
     * The switcher's GET form can only append `?soutez=`. The page turns that into a
     * 302 to the canonical path-scoped URL, so the control stays JS-free and the
     * component needs no change.
     */
    public function testSoutezQueryParameterRedirectsToThatSoutezsOwnUrl(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request(
            'GET',
            self::url(AppFixtures::PREMIUM_COMPETITION_ID, AppFixtures::MATCH_LIVE_ID)
                .'?soutez='.AppFixtures::BOOSTS_COMPETITION_ID,
        );

        self::assertResponseRedirects(self::url(AppFixtures::BOOSTS_COMPETITION_ID, AppFixtures::MATCH_LIVE_ID));

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('body', AppFixtures::BOOSTS_COMPETITION_NAME);
    }

    /**
     * An id that is unknown, foreign, or names a soutěž that EXCLUDES this match
     * must not 403 and must not redirect — the page stays on the soutěž in the path.
     * Guessing a UUID must never reveal that it exists.
     */
    public function testForeignUnknownOrExcludingSoutezFallsBackWithoutLeaking(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $here = self::url(AppFixtures::PREMIUM_COMPETITION_ID, AppFixtures::MATCH_LIVE_ID);

        // Someone else's soutěž, on another zdroj entirely.
        $client->request('GET', $here.'?soutez='.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', AppFixtures::VERIFIED_COMPETITION_NAME);

        // A soutěž the viewer really is in, which does not include this match.
        $client->request('GET', $here.'?soutez='.AppFixtures::SUBSET_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('body', AppFixtures::PREMIUM_COMPETITION_NAME);

        // A UUID that is nothing at all.
        $client->request('GET', $here.'?soutez=0197b3c4-0000-7000-8000-000000000001');
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.mc-tip-head', 'Váš tip');
    }

    // ─────────────────────── Pořadí za zápas ───────────────────────

    /**
     * „Pořadí za zápas" reveals concrete tips, so before the match has a result it
     * needs the OthersTips entitlement. A member without it gets the locked twin and
     * a working buy CTA — being the soutěž's own member buys no free pass.
     */
    public function testRankingIsPaywalledWithoutTheOthersTipsEntitlement(): void
    {
        $client = static::createClient();
        $this->joinAndTip(AppFixtures::VERIFIED_USER_ID, 1, 0);
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::url(AppFixtures::BOOSTS_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID));
        self::assertResponseIsSuccessful();

        self::assertAnySelectorTextContains('h2', 'Pořadí za zápas');
        self::assertCount(0, $crawler->filter('table.lb-table'));
        self::assertGreaterThanOrEqual(
            1,
            $crawler->filter('input[value="others_tips"]')->count(),
            'The locked ranking must carry a working „odemknout" CTA.',
        );
    }

    /** SECOND_VERIFIED_USER holds the OthersTips boost in the boosts competition. */
    public function testRankingIsVisibleWithTheOthersTipsBoost(): void
    {
        $client = static::createClient();
        $this->joinAndTip(AppFixtures::VERIFIED_USER_ID, 1, 0);
        $this->testCommandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 2,
        ));
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::url(AppFixtures::BOOSTS_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID));
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('table.lb-table'));
        // The other member's concrete tip is what the entitlement sells.
        self::assertAnySelectorTextContains('table.lb-table', '1:0');
        self::assertAnySelectorTextContains('table.lb-table', AppFixtures::VERIFIED_USER_NICKNAME);
        self::assertCount(0, $crawler->filter('input[value="others_tips"]'));
    }

    /**
     * „Pořadí za zápas" is the ONE list of other members' tips (item 22 folded „Jak
     * tipovali ostatní" into it), so the optional tip parts that only the folded-away
     * block used to show — periods, prodloužení, střelci — must appear on its rows.
     */
    public function testTheRankingCarriesTheOptionalTipPartsOfTheFoldedAwayList(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        // SUBSET_GUESS: 2:1 with period tips [[1,0],[1,1]] and one scorer, on the
        // finished match — a result, so the tips are public to every member.
        $crawler = $client->request('GET', self::url(AppFixtures::SUBSET_COMPETITION_ID, AppFixtures::MATCH_FINISHED_ID));
        self::assertResponseIsSuccessful();

        $table = $crawler->filter('table.lb-table')->text();
        self::assertStringContainsString('(1:0, 1:1)', $table);
        self::assertStringContainsString(AppFixtures::PLAYER_HOME_SCORER_ONE_NAME, $table);
    }

    // ─────────────────────────── team form ───────────────────────────

    /**
     * Team form is computed from the soutěž's finished matches, and is simply absent
     * for a team that has not played — never „V0 R0 P0".
     */
    public function testTeamFormIsComputedAndAbsentWhenThereIsNothingToCompute(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        // Bohemians 2:1 Jablonec is the one finished match of the curated zdroj.
        $client->request('GET', self::url(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::MATCH_FINISHED_ID));
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.mc-form', 'V1 R0 P0');
        self::assertAnySelectorTextContains('.mc-form', 'V0 R0 P1');

        // Sparta / Slavia have played nothing yet.
        $client->request('GET', self::url(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'V0 R0 P0');
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

    private function joinAndTip(string $userId, int $home, int $away): void
    {
        $this->testCommandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString($userId),
            token: AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ));
        $this->testCommandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString($userId),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: $home,
            awayScore: $away,
        ));
    }
}
