<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Enum\CompetitionMatchSelectionMode;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Item 26 — competition detail gains a read-only „Pravidla" dialog and loses the
 * „Týmy soutěže" pill row.
 *
 * The assertion that would rot first is the one about WHO may open the rules:
 * scoring rules are what a *player* needs to understand their points, so the
 * button sits in an action bar that used to render for organizers only. Every
 * role therefore gets its own test below.
 */
final class CompetitionDetailRulesModalTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string OWN_DETAIL = '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID;
    private const string BOOSTS_DETAIL = '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID;

    // ── Who may open the rules ──────────────────────────────────────────────

    /**
     * The criterion that rots: SECOND_VERIFIED_USER is a plain, non-owner member
     * of BOOSTS_COMPETITION — no `competition_edit`, no admin role — and must
     * still reach the scoring rules without a detour through „Nastavení".
     */
    public function testAPlainMemberCanOpenTheRules(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        // No organizer action is offered to them…
        self::assertCount(0, $crawler->filter('a[href="/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID.'/nastaveni"]'));

        // …but the action bar exists for the one thing a player needs.
        self::assertCount(1, $crawler->filter('button[data-action="modal#open"]'));
        self::assertSelectorTextContains('button[data-action="modal#open"]', 'Pravidla');

        // The dialog itself is server-rendered, with this competition's points.
        $dialog = $crawler->filter('dialog[data-modal-target="dialog"]');
        self::assertCount(1, $dialog);
        self::assertStringContainsString('Přesný výsledek', $dialog->text());
        self::assertStringContainsString('5 b.', $dialog->text());
        self::assertStringContainsString('Správný tip výsledku', $dialog->text());
        self::assertStringContainsString('3 b.', $dialog->text());
    }

    public function testTheOrganizerSeesTheRulesNextToTheOrganizerActions(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::OWN_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('button[data-action="modal#open"]'));
        self::assertCount(1, $crawler->filter('a[href="/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/nastaveni"]'));
        self::assertStringContainsString('Přesný výsledek', $crawler->filter('dialog[data-modal-target="dialog"]')->text());
    }

    public function testAnAdminOwnerSeesTheRulesToo(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('button[data-action="modal#open"]'));
        self::assertStringContainsString('Přesný výsledek', $crawler->filter('dialog[data-modal-target="dialog"]')->text());
    }

    // ── What the dialog is, and is not ──────────────────────────────────────

    /**
     * Only ENABLED rules are listed — the read-only surface must not leak the
     * organizer's switched-off ones. BOOSTS_COMPETITION stores four enabled
     * rules; „Trefený střelec" and the period rules are off by default.
     */
    public function testOnlyEnabledRulesAreListed(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        $text = $crawler->filter('dialog[data-modal-target="dialog"]')->text();

        self::assertStringNotContainsString('Trefený střelec', $text);
        self::assertStringNotContainsString('Přesný výsledek části zápasu', $text);
        self::assertCount(4, $crawler->filter('dialog[data-modal-target="dialog"] li'));
    }

    /**
     * Keyboard-closable and named: a native <dialog> handles Esc itself, so what
     * the markup has to carry is the accessible name and visible close controls
     * (the ✕ and „Zavřít"), plus the controller that opens it.
     */
    public function testTheDialogIsNamedAndHasVisibleCloseControls(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);

        self::assertCount(1, $crawler->filter('[data-controller="modal"]'));
        self::assertCount(1, $crawler->filter('dialog[aria-labelledby="rules-modal-title"]'));
        self::assertCount(1, $crawler->filter('#rules-modal-title'));
        self::assertCount(2, $crawler->filter('dialog[data-modal-target="dialog"] button[data-action="modal#close"]'));
    }

    /**
     * A rules dialog reveals scoring CONFIGURATION, never anybody's tips —
     * managers and admins get no free entitlement pass and neither does this.
     */
    public function testTheDialogCarriesNoForm(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);

        self::assertCount(0, $crawler->filter('dialog[data-modal-target="dialog"] form'));
        self::assertCount(0, $crawler->filter('dialog[data-modal-target="dialog"] input'));
    }

    // ── „Týmy soutěže" is gone (the display, not the scope) ─────────────────

    public function testTheTeamPillRowIsGoneButTheTeamScopeStillFilters(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $competitionId = $this->createTeamsCompetition();

        $crawler = $client->request('GET', '/souteze/'.$competitionId);
        self::assertResponseIsSuccessful();

        // The display is gone…
        self::assertStringNotContainsString('Týmy soutěže', $crawler->html());

        // …the scope is not: only matches where Sparta or Real Madrid play.
        $matchCount = $crawler->filter('#zapasy li[data-reveal-target="item"]')->count();
        self::assertGreaterThan(0, $matchCount);
        self::assertLessThan(
            $this->allModeMatchCount($client),
            $matchCount,
            'A teams-mode competition must still include FEWER matches than mode `all`.',
        );
    }

    private function allModeMatchCount(KernelBrowser $client): int
    {
        $competitionId = $this->dispatchCreate(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Všechny zápasy',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
        ));

        return $client->request('GET', '/souteze/'.$competitionId)
            ->filter('#zapasy li[data-reveal-target="item"]')
            ->count();
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
