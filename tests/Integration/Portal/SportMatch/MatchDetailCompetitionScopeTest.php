<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Enum\CompetitionMatchSelectionMode;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * B4 — the match detail page must never silently omit a competition the viewer
 * is a member of. When the competition lives on this match's source but its
 * match scope leaves the match out (Subset / Teams / playoff-excluded), the page
 * names the competition and says why it is not tippable here.
 *
 * The scope itself is NOT redefined here: it always comes from
 * CompetitionMatchProvider, so these tests double as coverage that the page
 * agrees with the one authority.
 */
final class MatchDetailCompetitionScopeTest extends WebTestCase
{
    use WebFlowHelpers;

    /**
     * SUBSET_COMPETITION (owned by, and only joined by, SECOND_VERIFIED_USER)
     * selects MATCH_SCHEDULED + MATCH_FINISHED only. On MATCH_LIVE it must show
     * up in the explanation panel, not vanish.
     */
    public function testSubsetCompetitionOutsideItsSelectionIsNamedWithAReason(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_LIVE_ID);

        self::assertResponseIsSuccessful();
        // Two all-mode competitions of the same user still take tips here.
        self::assertSelectorTextContains('h2', 'Vaše tipy');
        self::assertAnySelectorTextContains('section', AppFixtures::PREMIUM_COMPETITION_NAME);

        self::assertAnySelectorTextContains('h3', 'Proč tu nejsou všechny vaše soutěže');
        self::assertAnySelectorTextContains('section', AppFixtures::SUBSET_COMPETITION_NAME);
        self::assertAnySelectorTextContains('section', 'jen ručně vybrané zápasy zdroje');
    }

    public function testSubsetCompetitionInsideItsSelectionNeedsNoExplanation(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('section', AppFixtures::SUBSET_COMPETITION_NAME);
        self::assertSelectorNotExists('h3:contains("Proč tu nejsou všechny vaše soutěže")');
    }

    /**
     * The reported shape: a Teams-mode competition over the same source that
     * simply does not cover this fixture. Reproduced here with fixture teams;
     * DevFixtures carries the same shape („Fandíme Česku", Česko + Slovensko
     * over the shared MS-2026 source) for browsing it by hand.
     */
    public function testTeamsModeCompetitionWithoutEitherTeamIsNamedWithItsTeams(): void
    {
        $client = static::createClient();
        $this->createTeamsCompetitionForVerifiedUser();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        // Plzeň vs Baník — neither is a filter team.
        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_LIVE_ID);

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('h3', 'Tenhle zápas se ve vašich soutěžích netipuje');
        self::assertAnySelectorTextContains('section', 'Sparta & Real');
        self::assertAnySelectorTextContains('section', 'jen zápasy vybraných týmů');
        // The filter teams are spelled out, so the absence is self-explanatory.
        self::assertAnySelectorTextContains('section', 'Sparta Praha');
        self::assertAnySelectorTextContains('section', 'Real Madrid');

        // A competition of the viewer's over ANOTHER source is not noise here.
        self::assertSelectorTextNotContains('body', AppFixtures::VERIFIED_COMPETITION_NAME);
    }

    public function testTeamsModeCompetitionCoveringTheMatchTakesTipsAsUsual(): void
    {
        $client = static::createClient();
        $this->createTeamsCompetitionForVerifiedUser();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        // Sparta vs Slavia — Sparta is a filter team.
        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Vaše tipy');
        self::assertAnySelectorTextContains('section', 'Sparta & Real');
        self::assertSelectorNotExists('h3:contains("netipuje")');
    }

    public function testPlayoffExcludingCompetitionExplainsItselfOnAPlayoffMatch(): void
    {
        $client = static::createClient();

        $this->testCommandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Jen základní část',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            includePlayoff: false,
        ));

        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_PLAYOFF_ID);

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('section', 'Jen základní část');
        self::assertAnySelectorTextContains('section', 'jen na základní část');
    }

    /**
     * No membership on this source at all ⇒ nothing to explain; the section
     * stays off the page entirely (VERIFIED_USER only plays on the private
     * source in the test baseline).
     */
    public function testNoSameSourceMembershipRendersNoSection(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/zapasy/'.AppFixtures::MATCH_LIVE_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('h2:contains("Vaše tipy")');
        self::assertSelectorNotExists('h3:contains("netipuje")');
    }

    private function createTeamsCompetitionForVerifiedUser(): void
    {
        $this->testCommandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Sparta & Real',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            selectionMode: CompetitionMatchSelectionMode::Teams,
            filterTeamIds: [
                Uuid::fromString(AppFixtures::TEAM_SPARTA_ID),
                Uuid::fromString(AppFixtures::TEAM_REAL_MADRID_ID),
            ],
        ));
    }
}
