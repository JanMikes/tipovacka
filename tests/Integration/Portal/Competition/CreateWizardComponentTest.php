<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionInvitation;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\CompetitionTeamFilter;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchSourceKind;
use App\Rule\ExactScoreRule;
use App\Rule\OvertimeExactRule;
use App\Rule\PeriodAwayGoalsRule;
use App\Rule\PeriodExactRule;
use App\Rule\PeriodHomeGoalsRule;
use App\Rule\PeriodTendencyRule;
use App\Rule\ScorerHitRule;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * S08 create-competition wizard: per-step validation, and the two full happy
 * paths (from-scratch hockey, curated subset + custom rules + premium intent).
 */
final class CreateWizardComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testEmptyNameBlocksAdvancing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component->call('next')->render();

        self::assertStringContainsString('Zadejte prosím název soutěže', $html);
        self::assertStringContainsString('Krok 1 ze 4', $html);
    }

    public function testNeitherSourceNorFromScratchBlocksAdvancing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component->set('name', 'Bez zdroje')->call('next')->render();

        self::assertStringContainsString('Vyberte zdroj zápasů', $html);
        self::assertStringContainsString('Krok 1 ze 4', $html);
    }

    public function testSubsetWithZeroMatchesBlocksAdvancing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component
            ->set('name', 'Vybrané')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('selectionMode', 'subset')
            ->set('selectedMatchIds', [])
            ->call('next')
            ->render();

        self::assertStringContainsString('Vyberte prosím alespoň jeden zápas', $html);
        self::assertStringContainsString('Krok 1 ze 4', $html);
    }

    public function testValidBasicsAdvanceToRules(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component
            ->set('name', 'Postup dál')
            ->set('fromScratch', true)
            ->set('sportId', Sport::HOCKEY_ID)
            ->call('next')
            ->render();

        self::assertStringContainsString('Krok 2 ze 4', $html);
        self::assertStringContainsString('Vyberte pravidla', $html);
    }

    public function testFromScratchHockeyHappyPath(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        // Walk the flow, then submit on the final step.
        $response = $component
            ->set('name', 'Hokej od začátku')
            ->set('fromScratch', true)
            ->set('sportId', Sport::HOCKEY_ID)
            ->call('next')   // → rules
            ->call('next')   // → invites
            ->call('next')   // → support
            ->set('monetization', 'boosts')
            ->call('submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertMatchesRegularExpression('#^/turnaje/[0-9a-f-]{36}$#', (string) $response->headers->get('Location'));

        $competition = $this->competitionByName($client, 'Hokej od začátku');
        self::assertSame(MatchSourceKind::Private, $competition->headlineSource->kind);
        self::assertSame('hockey', $competition->headlineSource->sport->code);
        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
        self::assertCount(1, $this->memberships($client, $competition->id));

        // Redirect points at the freshly created hidden source (empty-state „Přidejte zápasy").
        self::assertSame('/turnaje/'.$competition->headlineSource->id->toRfc4122(), $response->headers->get('Location'));
    }

    public function testCuratedSubsetCustomRulesPremiumHappyPath(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $response = $component
            ->set('name', 'Kurátor prémium')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('selectionMode', 'subset')
            ->set('selectedMatchIds', [AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_PLAYOFF_ID])
            ->set('enabledRuleIds', [
                'correct_home_goals',
                'correct_away_goals',
                'correct_outcome',
                ExactScoreRule::IDENTIFIER,
                ScorerHitRule::IDENTIFIER,
            ])
            ->set('rulePoints', [ExactScoreRule::IDENTIFIER => 8])
            ->set('withPin', true)
            ->set('inviteEmailsRaw', 'novy-hrac@example.com')
            ->set('monetization', 'premium')
            ->call('submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());

        $competition = $this->competitionByName($client, 'Kurátor prémium');
        self::assertSame('/souteze/'.$competition->id->toRfc4122(), $response->headers->get('Location'));
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertNotNull($competition->pin);

        $em = $this->em($client);

        // Subset selection rows.
        $selections = $em->createQueryBuilder()
            ->select('s')->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :c')->setParameter('c', $competition->id)
            ->getQuery()->getResult();
        self::assertCount(2, $selections);

        // Rule rows: changed point value + an enabled optional rule.
        self::assertSame(8, $this->rule($client, $competition->id, ExactScoreRule::IDENTIFIER)->points);
        self::assertTrue($this->rule($client, $competition->id, ScorerHitRule::IDENTIFIER)->enabled);

        // Invitation + stub user created (invitations "sent" via post-commit event).
        $invitations = $em->createQueryBuilder()
            ->select('i')->from(CompetitionInvitation::class, 'i')
            ->where('i.competition = :c')->setParameter('c', $competition->id)
            ->andWhere('i.email = :e')->setParameter('e', 'novy-hrac@example.com')
            ->getQuery()->getResult();
        self::assertCount(1, $invitations);

        $stub = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')->setParameter('e', 'novy-hrac@example.com')
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(User::class, $stub);
    }

    /**
     * W6 — step 1 exposes all THREE match-scope modes. `teams` has been wired end
     * to end since the team-filter feature landed; this pins the wizard UI so the
     * mode can never silently drop off the step again.
     * W2 — and the playoff toggle is NOT on step 1 any more.
     */
    public function testStepOneOffersThreeMatchScopeModesWithoutPlayoffToggle(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)->render();

        self::assertStringContainsString('Všechny zápasy', $html);
        self::assertStringContainsString('Podle týmu', $html);
        self::assertStringContainsString('Vybrat jen některé zápasy', $html);
        self::assertStringContainsString('value="teams"', $html);

        // The playoff question belongs to the layer being composed, so it is
        // right here with the mode radios — and it is the ONLY control writing
        // the flag.
        self::assertStringContainsString('Dohrávat turnaj?', $html);
        self::assertSame(1, substr_count($html, 'data-model="includePlayoff"'));
    }

    /**
     * The regression that shipped: the „add another zdroj" affordance was gated
     * on the basket already holding something, and it was ALSO the only way to
     * put the first thing in it — so from a fresh wizard the whole multi-zdroj
     * feature was unreachable. This drives the surface a user actually sees.
     */
    public function testAFreshWizardOffersTheWayIntoASecondZdroj(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);

        // Nothing picked yet: no half-offer, just the source picker.
        $blank = (string) $component->set('name', 'Cesta ke druhému zdroji')->render();
        self::assertStringNotContainsString('addLayerAndContinue', $blank);

        // The moment a zdroj is chosen, the way to add another must be there.
        $picked = (string) $component->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)->render();
        self::assertStringContainsString('Přidat další zdroj zápasů', $picked);
        self::assertStringContainsString('addLayerAndContinue', $picked);

        // Using it banks the first zdroj and hands back an empty editor.
        $second = (string) $component->call('addLayerAndContinue')->render();
        self::assertStringContainsString(AppFixtures::PUBLIC_SOURCE_NAME, $second, 'the first zdroj should now be a basket card');
        self::assertStringNotContainsString('value="'.AppFixtures::PUBLIC_SOURCE_ID.'"', $second, 'and should no longer be offered in the picker');
    }

    /**
     * „Pokračovat" alone must still build a one-zdroj soutěž — the common path
     * cannot require discovering the basket first.
     */
    public function testContinueAloneStillBanksTheChosenZdroj(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);

        $component
            ->set('name', 'Jen pokračovat')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('next')
            ->call('next')
            ->call('next')
            ->call('submit');

        $competition = $this->competitionByName($client, 'Jen pokračovat');

        self::assertCount(1, $competition->sources);
        self::assertSame(AppFixtures::PUBLIC_SOURCE_ID, $competition->sources[0]->matchSource->id->toRfc4122());
    }

    /**
     * The reason the basket exists: one soutěž composed from two zdroje, each
     * with its own scope. The wizard must create exactly that — not the first
     * zdroj with the second quietly dropped.
     */
    public function testComposesACompetitionFromTwoZdrojeWithTheirOwnScopes(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);

        $basket = (string) $component
            ->set('name', 'Dva zdroje')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('selectionMode', 'teams')
            ->set('selectedTeamIdsCsv', AppFixtures::TEAM_SPARTA_ID)
            ->call('addLayer')
            ->set('sourceId', AppFixtures::PRIVATE_SOURCE_ID)
            ->set('selectionMode', 'all')
            ->call('addLayer')
            ->render();

        // Both zdroje are in the basket, each stating its own scope.
        self::assertStringContainsString('Jen zápasy vybraných týmů (1)', $basket);
        self::assertStringContainsString('Všechny zápasy', $basket);

        $component->call('next')->call('next')->call('next')->call('submit');

        $competition = $this->competitionByName($client, 'Dva zdroje');

        self::assertCount(2, $competition->sources);
        self::assertTrue($competition->isMultiSource);

        $scopeBySource = [];

        foreach ($competition->sources as $layer) {
            $scopeBySource[$layer->matchSource->id->toRfc4122()] = $layer->selectionMode;
        }

        self::assertSame(CompetitionMatchSelectionMode::Teams, $scopeBySource[AppFixtures::PUBLIC_SOURCE_ID]);
        self::assertSame(CompetitionMatchSelectionMode::All, $scopeBySource[AppFixtures::PRIVATE_SOURCE_ID]);
    }

    /**
     * One sport per soutěž: the rules are configured once, in the sport's own
     * vocabulary. Rather than letting someone pick a hockey zdroj and then
     * refusing it, the picker stops offering other sports the moment the first
     * layer fixes one — and a zdroj already in the basket stops being offered
     * too, since adding it twice would mean the same union.
     */
    public function testThePickerNarrowsToTheChosenSportAndDropsBasketedZdroje(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $hockeySourceId = $this->seedHockeySource($client);

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);

        // Before anything is chosen, both sports are on offer.
        $empty = (string) $component->set('name', 'Jeden sport')->render();
        self::assertStringContainsString($hockeySourceId, $empty);

        $afterFootball = (string) $component
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('addLayer')
            ->call('startLayer')
            ->render();

        self::assertStringNotContainsString($hockeySourceId, $afterFootball);
        // …and the football zdroj already in the basket is not offered again.
        self::assertStringNotContainsString('value="'.AppFixtures::PUBLIC_SOURCE_ID.'"', $afterFootball);
        // The other football zdroj still is.
        self::assertStringContainsString(AppFixtures::PRIVATE_SOURCE_ID, $afterFootball);
    }

    /**
     * W1 — „Dohrávat turnaj?": ONE control, not two. It sits in the layer
     * editor because the flag lives on the zdroj's scope layer; a later step
     * has no layer in hand to write it to. Once the layer is committed the
     * control is gone, and its answer is visible on the basket card instead.
     */
    public function testPlayoffToggleIsWordedAsDohravatTurnajAndLeavesWithItsLayer(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);

        $editing = (string) $component
            ->set('name', 'Playoff u vrstvy')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->render();

        self::assertStringContainsString('Dohrávat turnaj?', $editing);
        self::assertStringContainsString('zahrnout playoff zápasy', $editing);
        self::assertSame(1, substr_count($editing, 'data-model="includePlayoff"'));

        // Committed: the toggle went with the editor, and the card states the answer.
        $committed = (string) $component
            ->set('includePlayoff', false)
            ->call('addLayer')
            ->render();

        self::assertStringNotContainsString('data-model="includePlayoff"', $committed);
        self::assertStringContainsString('Všechny zápasy kromě playoff', $committed);
    }

    /**
     * W1 — the rules step offers Standardní / Maxi / Vlastní (připravujeme),
     * with Standardní pre-selected (it is exactly what mount() enables) and
     * Vlastní disabled.
     */
    public function testRulesStepOffersStandardMaxiAndDisabledCustomPresets(): void
    {
        $html = $this->renderRulesStep();

        self::assertStringContainsString('Standardní', $html);
        self::assertStringContainsString('>Maxi<', $html);
        self::assertStringContainsString('Vlastní (připravujeme)', $html);
        self::assertStringContainsString('scoring-preset#maxi', $html);

        // Standardní is the pre-selected tile now that Vlastní is disabled.
        self::assertMatchesRegularExpression('~variant-card selected[^>]*scoring-preset\#standard"~', $html);
        self::assertStringNotContainsString('scoring-preset#custom', $html);
        self::assertMatchesRegularExpression('#<button[^>]*variant-card"[^>]*disabled#', $html);

        // The Maxi preset is served from PHP — base rules + the per-period trio
        // + the after-overtime score.
        $presets = $this->presetsFrom($html);
        self::assertArrayHasKey('maxi', $presets);
        self::assertSame(
            [
                'correct_away_goals',
                'correct_home_goals',
                'correct_outcome',
                'exact_score',
                'period_exact',
                'period_away_goals',
                'period_home_goals',
                'overtime_exact',
            ],
            $presets['maxi'],
        );
        self::assertSame(
            ['correct_away_goals', 'correct_home_goals', 'correct_outcome', 'exact_score'],
            $presets['standard'],
        );
    }

    /** W1 — the four Standardní rules carry the renamed copy. */
    public function testRulesStepUsesRenamedStandardRuleCopy(): void
    {
        $html = $this->renderRulesStep();

        self::assertStringContainsString('Tip hosté', $html);
        self::assertStringContainsString('Správný tip hostujícího týmu', $html);
        self::assertStringContainsString('Tip domácí', $html);
        self::assertStringContainsString('Správný tip domácího týmu', $html);
        self::assertStringContainsString('Dobrý tip výsledku', $html);
        self::assertStringContainsString('Přesný tip výsledku', $html);
        self::assertStringContainsString('bonus za obě uhodnutá skóre', $html);

        self::assertStringNotContainsString('Dobrý tip skóre hostů', $html);
        self::assertStringNotContainsString('Dobrý tip skóre domácích', $html);
        self::assertStringNotContainsString('Trefená obě skóre současně', $html);
    }

    /**
     * W1 — the per-period goal rules exist alongside the KEPT „Tendence části
     * zápasu", scorers are asked as a question, and PP/PEN stay ONE combined
     * overtime entry.
     */
    public function testRulesStepOffersPeriodGoalRulesScorerQuestionAndOneOvertimeEntry(): void
    {
        $html = $this->renderRulesStep();

        self::assertStringContainsString('Přesný tip části zápasu', $html);
        self::assertStringContainsString('Tip hosté v části zápasu', $html);
        self::assertStringContainsString('Tip domácí v části zápasu', $html);
        self::assertStringContainsString('Tendence části zápasu', $html);
        self::assertStringContainsString('data-rule="period_home_goals"', $html);
        self::assertStringContainsString('data-rule="period_away_goals"', $html);

        self::assertStringContainsString('Chcete tipovat také střelce utkání?', $html);

        // ONE combined „po prodloužení / penaltách" entry — PP and PEN are not split.
        self::assertSame(1, substr_count($html, 'Celkové skóre po prodloužení / penaltách'));
        self::assertStringNotContainsString('po PEN', $html);

        // Fantasy is deferred out of the wizard entirely.
        self::assertStringNotContainsStringIgnoringCase('fantasy', $html);
    }

    /** W1 — the Maxi rule set actually persists as enabled configuration rows. */
    public function testMaxiRuleSetPersistsPeriodAndOvertimeRules(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $response = $component
            ->set('name', 'Maxi soutěž')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('enabledRuleIds', [
                'correct_home_goals',
                'correct_away_goals',
                'correct_outcome',
                ExactScoreRule::IDENTIFIER,
                PeriodExactRule::IDENTIFIER,
                PeriodHomeGoalsRule::IDENTIFIER,
                PeriodAwayGoalsRule::IDENTIFIER,
                OvertimeExactRule::IDENTIFIER,
            ])
            ->call('submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());

        $competition = $this->competitionByName($client, 'Maxi soutěž');

        self::assertTrue($this->rule($client, $competition->id, PeriodExactRule::IDENTIFIER)->enabled);
        self::assertTrue($this->rule($client, $competition->id, PeriodHomeGoalsRule::IDENTIFIER)->enabled);
        self::assertTrue($this->rule($client, $competition->id, PeriodAwayGoalsRule::IDENTIFIER)->enabled);
        self::assertTrue($this->rule($client, $competition->id, OvertimeExactRule::IDENTIFIER)->enabled);

        // Nothing is retired: period_tendency still exists, just disabled by default.
        self::assertFalse($this->rule($client, $competition->id, PeriodTendencyRule::IDENTIFIER)->enabled);

        // Enabling only the per-period goal rules must still open the period tips.
        $features = $client->getContainer()->get(CompetitionGuessFeatures::class);
        $features->forgetCompetition($competition->id);
        self::assertTrue($features->featuresFor($competition->id)->periodTips);
    }

    /** W3 — the skip action carries no copy duplicating the step heading. */
    public function testInvitesStepSkipActionIsBare(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component
            ->set('name', 'Pozvánky')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('next')
            ->call('next')
            ->render();

        self::assertStringContainsString('Pozvěte hráče', $html);
        self::assertStringContainsString('>Přeskočit</button>', $html);
        self::assertStringNotContainsString('Přeskočit — pozvat můžete kdykoli později', $html);
    }

    /** W4 — the „Pozvete nás na pivo?" copy, with „Férová soutěž" pre-selected. */
    public function testSupportStepShowsBeerCopyWithFairCompetitionPreselected(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component
            ->set('name', 'Na pivo')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('next')
            ->call('next')
            ->call('next')
            ->render();

        self::assertStringContainsString('Pozvete nás na pivo?', $html);
        self::assertStringContainsString('Tipovačka je kompletně zdarma.', $html);
        self::assertStringContainsString('Teď už jen rozhodněte, jak budou ve vaší soutěži fungovat prémiové funkce.', $html);

        // Premium XOR boosts — the two choices, no third state.
        self::assertStringContainsString('Férová soutěž', $html);
        self::assertStringContainsString('Chci hrát Férovou soutěž', $html);
        self::assertStringContainsString('Volná volba Premium', $html);
        self::assertStringContainsString('Chci ponechat rozhodnutí na hráčích', $html);
        self::assertStringNotContainsString('Bez placených funkcí', $html);

        // „Férová soutěž" (= Premium) is the recommended, pre-selected default.
        self::assertStringContainsString('Doporučujeme', $html);
        self::assertMatchesRegularExpression('#value="premium"[^>]*checked#', $html);
        self::assertDoesNotMatchRegularExpression('#value="boosts"[^>]*checked#', $html);
    }

    /**
     * W6 — the „Podle týmu" happy path: a competition scoped to Sparta includes
     * exactly the one fixture match Sparta plays in.
     */
    public function testTeamsModeHappyPath(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $response = $component
            ->set('name', 'Jen Sparta')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('selectionMode', 'teams')
            ->set('selectedTeamIdsCsv', AppFixtures::TEAM_SPARTA_ID)
            ->call('next')
            ->call('next')
            ->call('next')
            ->call('submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());

        $competition = $this->competitionByName($client, 'Jen Sparta');
        self::assertSame(CompetitionMatchSelectionMode::Teams, $competition->sources[0]->selectionMode);

        $filters = $this->em($client)->createQueryBuilder()
            ->select('f')->from(CompetitionTeamFilter::class, 'f')
            ->where('f.competition = :c')->setParameter('c', $competition->id)
            ->getQuery()->getResult();
        self::assertCount(1, $filters);

        // The provider — the single authority on membership — agrees.
        /** @var CompetitionMatchProvider $provider */
        $provider = $client->getContainer()->get(CompetitionMatchProvider::class);
        $matches = $provider->matchesFor($competition);
        self::assertCount(1, $matches);
        self::assertSame(AppFixtures::MATCH_SCHEDULED_ID, $matches[0]->id->toRfc4122());
    }

    /** An empty team pick cannot advance past step 1. */
    public function testTeamsModeWithZeroTeamsBlocksAdvancing(): void
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);
        $html = (string) $component
            ->set('name', 'Bez týmů')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->set('selectionMode', 'teams')
            ->set('selectedTeamIdsCsv', '')
            ->call('next')
            ->render();

        self::assertStringContainsString('Vyberte prosím alespoň jeden tým', $html);
        self::assertStringContainsString('Krok 1 ze 4', $html);
    }

    // ---- helpers ----

    /** Renders step 2 („Pravidla") over a curated source. */
    /** A curated zdroj of ANOTHER sport, so the sport lock has something to refuse. */
    private function seedHockeySource(KernelBrowser $client): string
    {
        $em = $this->em($client);

        $sport = $em->find(Sport::class, Uuid::fromString(Sport::HOCKEY_ID));
        self::assertInstanceOf(Sport::class, $sport);
        $owner = $this->user($client, AppFixtures::ADMIN_ID);

        $source = new MatchSource(
            id: Uuid::v7(),
            sport: $sport,
            owner: $owner,
            kind: MatchSourceKind::Curated,
            name: 'Hokejová extraliga',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $source->popEvents();
        $em->persist($source);
        $em->flush();

        return $source->id->toRfc4122();
    }

    private function renderRulesStep(): string
    {
        $client = static::createClient();
        $client->loginUser($this->user($client, AppFixtures::VERIFIED_USER_ID));

        $component = $this->createLiveComponent('Competition:CreateWizard', [], $client);

        return (string) $component
            ->set('name', 'Pravidla')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('next')
            ->render();
    }

    /**
     * @return array<string, list<string>>
     */
    private function presetsFrom(string $html): array
    {
        self::assertSame(1, preg_match('#data-scoring-preset-presets-value="([^"]+)"#', $html, $matches));

        $decoded = json_decode(html_entity_decode($matches[1], \ENT_QUOTES), true);
        self::assertIsArray($decoded);

        /* @var array<string, list<string>> $decoded */
        return $decoded;
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private function user(KernelBrowser $client, string $id): User
    {
        $user = $this->em($client)->find(User::class, Uuid::fromString($id));
        self::assertNotNull($user);

        return $user;
    }

    private function competitionByName(KernelBrowser $client, string $name): Competition
    {
        $this->em($client)->clear();

        $competition = $this->em($client)->createQueryBuilder()
            ->select('c')->from(Competition::class, 'c')
            ->where('c.name = :name')->setParameter('name', $name)
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function rule(KernelBrowser $client, Uuid $competitionId, string $identifier): CompetitionRuleConfiguration
    {
        $rule = $this->em($client)->createQueryBuilder()
            ->select('r')->from(CompetitionRuleConfiguration::class, 'r')
            ->where('r.competition = :c')->setParameter('c', $competitionId)
            ->andWhere('r.ruleIdentifier = :i')->setParameter('i', $identifier)
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(CompetitionRuleConfiguration::class, $rule);

        return $rule;
    }

    /**
     * @return list<Membership>
     */
    private function memberships(KernelBrowser $client, Uuid $competitionId): array
    {
        return $this->em($client)->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.competition = :c')->setParameter('c', $competitionId)
            ->getQuery()->getResult();
    }
}
