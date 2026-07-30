<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Enum\CompetitionMatchSelectionMode;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * B4 — the match page must never silently omit a soutěž the viewer is a member of.
 * When the soutěž lives on this match's zdroj zápasů but its match scope leaves the
 * match out (Subset / Teams / playoff-excluded), the page names it and says why it
 * is not tippable here.
 *
 * The scope itself is NOT redefined here: it always comes from
 * CompetitionMatchProvider, so these tests double as coverage that the page agrees
 * with the one authority.
 *
 * Item 22 moved the panel onto `/souteze/{cid}/zapasy/{mid}`. That REMOVES one of
 * B4's states rather than breaking it: a viewer can only be on this page through a
 * soutěž that includes the match, so „tenhle zápas se ve vašich soutěžích netipuje"
 * (no including soutěž at all) is unreachable — the page itself is. The invariant
 * that mattered is intact: the switcher lists what INCLUDES the match, the panel
 * explains what EXCLUDES it, and the two sets stay disjoint.
 */
final class MatchDetailCompetitionScopeTest extends WebTestCase
{
    use WebFlowHelpers;

    private static function url(string $competitionId, string $sportMatchId): string
    {
        return '/souteze/'.$competitionId.'/zapasy/'.$sportMatchId;
    }

    /**
     * SUBSET_COMPETITION (owned by, and only joined by, SECOND_VERIFIED_USER)
     * selects MATCH_SCHEDULED + MATCH_FINISHED only. On MATCH_LIVE — seen through
     * one of the same viewer's all-mode soutěže — it must show up in the
     * explanation panel, not vanish.
     */
    public function testSubsetCompetitionOutsideItsSelectionIsNamedWithAReason(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', self::url(AppFixtures::PREMIUM_COMPETITION_ID, AppFixtures::MATCH_LIVE_ID));

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.mc-tip-head', 'Váš tip');
        self::assertAnySelectorTextContains('body', AppFixtures::PREMIUM_COMPETITION_NAME);

        self::assertAnySelectorTextContains('h3', 'Proč tu nejsou všechny vaše soutěže');
        self::assertAnySelectorTextContains('section', AppFixtures::SUBSET_COMPETITION_NAME);
        self::assertAnySelectorTextContains('section', 'jen ručně vybrané zápasy zdroje');
    }

    public function testSubsetCompetitionInsideItsSelectionNeedsNoExplanation(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', self::url(AppFixtures::SUBSET_COMPETITION_ID, AppFixtures::MATCH_SCHEDULED_ID));

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('body', AppFixtures::SUBSET_COMPETITION_NAME);
        self::assertSelectorNotExists('h3:contains("Proč tu nejsou všechny vaše soutěže")');
    }

    /**
     * The reported shape: a Teams-mode soutěž over the same zdroj that simply does
     * not cover this fixture. Reproduced here with fixture teams; DevFixtures carries
     * the same shape („Fandíme Česku", Česko + Slovensko over the shared MS-2026
     * zdroj) for browsing it by hand.
     */
    public function testTeamsModeCompetitionWithoutEitherTeamIsNamedWithItsTeams(): void
    {
        $client = static::createClient();
        $allMode = $this->createAllModeCompetition('Celý zdroj');
        $this->createTeamsCompetition();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        // Plzeň vs Baník — neither is a filter team of „Sparta & Real".
        $client->request('GET', self::url($allMode, AppFixtures::MATCH_LIVE_ID));

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('h3', 'Proč tu nejsou všechny vaše soutěže');
        self::assertAnySelectorTextContains('section', 'Sparta & Real');
        self::assertAnySelectorTextContains('section', 'jen zápasy vybraných týmů');
        // The filter teams are spelled out, so the absence is self-explanatory.
        self::assertAnySelectorTextContains('section', 'Sparta Praha');
        self::assertAnySelectorTextContains('section', 'Real Madrid');

        // A soutěž of the viewer's over ANOTHER zdroj is not noise here.
        self::assertSelectorTextNotContains('body', AppFixtures::VERIFIED_COMPETITION_NAME);
    }

    public function testTeamsModeCompetitionCoveringTheMatchTakesTipsAsUsual(): void
    {
        $client = static::createClient();
        $teams = $this->createTeamsCompetition();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        // Sparta vs Slavia — Sparta is a filter team, so the soutěž includes it.
        $client->request('GET', self::url($teams, AppFixtures::MATCH_SCHEDULED_ID));

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.mc-tip-head', 'Váš tip');
        self::assertAnySelectorTextContains('body', 'Sparta & Real');
        self::assertSelectorNotExists('h3:contains("Proč tu nejsou všechny vaše soutěže")');
    }

    public function testPlayoffExcludingCompetitionExplainsItselfOnAPlayoffMatch(): void
    {
        $client = static::createClient();
        $allMode = $this->createAllModeCompetition('Celý zdroj');
        $this->dispatchCreate(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Jen základní část',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            includePlayoff: false,
        ));

        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::url($allMode, AppFixtures::MATCH_PLAYOFF_ID));

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('section', 'Jen základní část');
        self::assertAnySelectorTextContains('section', 'jen na základní část');
    }

    /**
     * Every soutěž of the viewer's on this zdroj takes a tip here ⇒ nothing to
     * explain, and the section stays off the page entirely. ADMIN's soutěže on the
     * curated zdroj are all mode `all`.
     */
    public function testNothingToExplainRendersNoSection(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('GET', self::url(AppFixtures::PUBLIC_COMPETITION_ID, AppFixtures::MATCH_LIVE_ID));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('h3:contains("Proč tu nejsou všechny vaše soutěže")');
    }

    private function createTeamsCompetition(): string
    {
        return $this->dispatchCreate(new CreateCompetitionCommand(
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

    private function createAllModeCompetition(string $name): string
    {
        return $this->dispatchCreate(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: $name,
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
        ));
    }

    private function dispatchCreate(CreateCompetitionCommand $command): string
    {
        $envelope = $this->testCommandBus()->dispatch($command);
        $handled = $envelope->last(HandledStamp::class);
        self::assertNotNull($handled);
        $competition = $handled->getResult();
        self::assertInstanceOf(Competition::class, $competition);

        return $competition->id->toRfc4122();
    }
}
