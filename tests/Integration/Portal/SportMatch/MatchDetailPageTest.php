<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Item 10 — the match detail page scopes EVERY number to one soutěž, picked with
 * `<twig:SoutezSwitcher>` and carried in `?soutez=`, and gates „Pořadí za zápas"
 * with the OthersTips entitlement (via TipVisibilityGate, never by hand).
 */
final class MatchDetailPageTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string SCHEDULED = '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID;
    private const string LIVE = '/zapasy/'.AppFixtures::MATCH_LIVE_ID;
    private const string FINISHED = '/zapasy/'.AppFixtures::MATCH_FINISHED_ID;

    /**
     * SECOND_VERIFIED_USER is in three competitions on the curated source: PREMIUM
     * and BOOSTS include MATCH_LIVE, SUBSET does not. The switcher offers exactly
     * the two that include it, and the B4 panel explains the third — the same
     * soutěž is never described by both.
     */
    public function testSwitcherOffersOnlyTheCompetitionsThatIncludeTheMatch(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::LIVE);
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

    public function testSoutezQueryParameterScopesThePage(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', self::LIVE.'?soutez='.AppFixtures::PREMIUM_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('section', AppFixtures::PREMIUM_COMPETITION_NAME);

        $client->request('GET', self::LIVE.'?soutez='.AppFixtures::BOOSTS_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('section', AppFixtures::BOOSTS_COMPETITION_NAME);
    }

    /**
     * A foreign or unknown id falls back to a soutěž the viewer really is in —
     * guessing a UUID must never reveal that it exists, let alone its name.
     */
    public function testForeignOrUnknownSoutezFallsBackWithoutLeaking(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        // VERIFIED_COMPETITION belongs to someone else, on another source entirely.
        $client->request('GET', self::LIVE.'?soutez='.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', AppFixtures::VERIFIED_COMPETITION_NAME);
        self::assertSelectorTextContains('h2', 'Váš tip');

        $client->request('GET', self::LIVE.'?soutez=0197b3c4-0000-7000-8000-000000000001');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Váš tip');
    }

    /**
     * „Pořadí za zápas" reveals concrete tips, so before the deadline it needs the
     * OthersTips entitlement. A member without it gets the locked twin + a working
     * buy CTA — being the competition's own member buys no free pass.
     */
    public function testRankingIsPaywalledWithoutTheOthersTipsEntitlement(): void
    {
        $client = static::createClient();
        $this->joinAndTip(AppFixtures::VERIFIED_USER_ID, 1, 0);
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::SCHEDULED.'?soutez='.AppFixtures::BOOSTS_COMPETITION_ID);
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

        $crawler = $client->request('GET', self::SCHEDULED.'?soutez='.AppFixtures::BOOSTS_COMPETITION_ID);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('table.lb-table'));
        // The other member's concrete tip is what the entitlement sells.
        self::assertAnySelectorTextContains('table.lb-table', '1:0');
        self::assertAnySelectorTextContains('table.lb-table', AppFixtures::VERIFIED_USER_NICKNAME);
        self::assertCount(0, $crawler->filter('input[value="others_tips"]'));
    }

    /**
     * Team form is computed from the soutěž's finished matches, and is simply
     * absent for a team that has not played — never „V0 R0 P0".
     */
    public function testTeamFormIsComputedAndAbsentWhenThereIsNothingToCompute(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        // Bohemians 2:1 Jablonec is the one finished match of the curated source.
        $client->request('GET', self::FINISHED.'?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('section', 'V1 R0 P0');
        self::assertAnySelectorTextContains('section', 'V0 R0 P1');

        // Sparta / Slavia have played nothing yet.
        $client->request('GET', self::SCHEDULED.'?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'V0 R0 P0');
    }

    /** The page is logged-in only and stays that way. */
    public function testAnonymousVisitorIsSentToTheLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', self::FINISHED);

        self::assertResponseRedirects();
        self::assertStringContainsString('/prihlaseni', (string) $client->getResponse()->headers->get('Location'));
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
