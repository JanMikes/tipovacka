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
use Symfony\Component\DomCrawler\Crawler;
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

    /**
     * „Pozvat přítele" put a SECOND `modal` dialog on this page, so every assertion here
     * names the rules one by its accessible name instead of counting dialogs. Counting
     * was never what these tests meant — they mean „the rules dialog is there, server
     * -rendered, read-only" — and a bare `dialog[data-modal-target="dialog"]` would now
     * silently read the invitation dialog's markup instead.
     */
    private const string RULES_DIALOG = 'dialog[aria-labelledby="rules-modal-title"]';

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
        self::assertSame(1, $this->modalTriggersLabelled($crawler, 'Pravidla'));

        // The dialog itself is server-rendered, with this competition's points.
        $dialog = $crawler->filter(self::RULES_DIALOG);
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

        self::assertSame(1, $this->modalTriggersLabelled($crawler, 'Pravidla'));
        self::assertCount(1, $crawler->filter('a[href="/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/nastaveni"]'));
        self::assertStringContainsString('Přesný výsledek', $crawler->filter(self::RULES_DIALOG)->text());
    }

    public function testAnAdminOwnerSeesTheRulesToo(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertSame(1, $this->modalTriggersLabelled($crawler, 'Pravidla'));
        self::assertStringContainsString('Přesný výsledek', $crawler->filter(self::RULES_DIALOG)->text());
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
        $text = $crawler->filter(self::RULES_DIALOG)->text();

        self::assertStringNotContainsString('Trefený střelec', $text);
        self::assertStringNotContainsString('Přesný výsledek části zápasu', $text);
        self::assertCount(4, $crawler->filter(self::RULES_DIALOG.' li'));
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

        // Inside its own `modal` scope — „Pozvat přítele" has a separate one.
        self::assertCount(1, $crawler->filter('[data-controller="modal"] '.self::RULES_DIALOG));
        self::assertCount(1, $crawler->filter(self::RULES_DIALOG));
        self::assertCount(1, $crawler->filter('#rules-modal-title'));
        self::assertCount(2, $crawler->filter(self::RULES_DIALOG.' button[data-action="modal#close"]'));
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

        self::assertCount(0, $crawler->filter(self::RULES_DIALOG.' form'));
        self::assertCount(0, $crawler->filter(self::RULES_DIALOG.' input'));
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

    /**
     * How many „open a dialog" triggers carry this label. Counting all of them stopped
     * saying anything the day „Pozvat přítele" added a second one to this page.
     */
    private function modalTriggersLabelled(Crawler $crawler, string $label): int
    {
        return $crawler->filter('button[data-action="modal#open"]')
            ->reduce(static fn (Crawler $node): bool => str_contains($node->text(), $label))
            ->count();
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
