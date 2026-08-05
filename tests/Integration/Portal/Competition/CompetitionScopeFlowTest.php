<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionSource;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\MatchSourceKind;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * „Zápasy soutěže" — the create wizard's step 1, reachable for the whole life of
 * a private soutěž. What the screen must guarantee: the organizer edits THEIR
 * competition, and only their own private rozpis is ever offered for editing.
 */
final class CompetitionScopeFlowTest extends WebTestCase
{
    use InteractsWithLiveComponents;
    use WebFlowHelpers;

    public function testOwnerSeesTheBasketOfTheirCompetition(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/zapasy');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Zápasy soutěže');
        // The soutěž's own private zdroj reads as „Vlastní zápasy", never as a zdroj.
        self::assertSelectorTextContains('body', 'Vlastní zápasy');
        self::assertSelectorTextContains('body', 'Přidat zdroj zápasů');
    }

    public function testTheOwnMatchesPanelListsTheRozpisOfTheCompetitionsOwnZdroj(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/zapasy');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Tygři');
        self::assertSelectorExists('a[href*="/zapasy/novy?soutez='.AppFixtures::VERIFIED_COMPETITION_ID.'"]');
    }

    /**
     * The whole point of the exclusivity rule: a soutěž over a CURATED zdroj is
     * shared with everyone, so the screen offers no way to touch its matches.
     */
    public function testACuratedOnlyCompetitionIsNotOfferedAnyMatchEditing(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', '/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="/zapasy/novy"]');
        self::assertSelectorTextNotContains('body', 'Nahrát rozpis');
    }

    public function testNonOwnerIsRefused(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/zapasy');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGlobalCompetitionIsSentToSettingsInstead(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('GET', '/souteze/'.AppFixtures::GLOBAL_COMPETITION_ID.'/zapasy');

        self::assertResponseRedirects('/souteze/'.AppFixtures::GLOBAL_COMPETITION_ID.'/nastaveni');
    }

    public function testAddingAZdrojThroughTheEditorAndSavingWidensTheCompetition(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);

        $component = $this->createLiveComponent(
            'Competition:ScopeEditor',
            ['competition' => $competition],
            $client,
        );

        // Open the editor, pick the curated zdroj, save without pressing „Přidat"
        // first — an open, usable editor commits itself.
        $response = $component
            ->call('startLayer')
            ->set('sourceId', AppFixtures::PUBLIC_SOURCE_ID)
            ->call('save')
            ->response();

        self::assertSame(302, $response->getStatusCode());

        $this->testEntityManager()->clear();

        $sourceIds = array_map(
            static fn (CompetitionSource $l): string => $l->matchSource->id->toRfc4122(),
            $this->competition(AppFixtures::VERIFIED_COMPETITION_ID)->sources,
        );

        self::assertContains(AppFixtures::PUBLIC_SOURCE_ID, $sourceIds);
        self::assertContains(AppFixtures::PRIVATE_SOURCE_ID, $sourceIds);
    }

    public function testRemovingEveryZdrojIsRefused(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $component = $this->createLiveComponent(
            'Competition:ScopeEditor',
            ['competition' => $this->competition(AppFixtures::VERIFIED_COMPETITION_ID)],
            $client,
        );

        $html = (string) $component->call('removeLayer', ['index' => 0])->call('save')->render();

        self::assertStringContainsString('Soutěž musí mít aspoň jeden zdroj zápasů', $html);
        self::assertCount(1, $this->competition(AppFixtures::VERIFIED_COMPETITION_ID)->sources);
    }

    public function testAddingOwnMatchesCreatesTheCompetitionsOwnPrivateZdroj(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $component = $this->createLiveComponent(
            'Competition:ScopeEditor',
            ['competition' => $this->competition(AppFixtures::SUBSET_COMPETITION_ID)],
            $client,
        );

        $response = $component->call('addOwnMatchesLayer')->call('save')->response();

        self::assertSame(302, $response->getStatusCode());

        $this->testEntityManager()->clear();

        $layers = $this->competition(AppFixtures::SUBSET_COMPETITION_ID)->sources;
        self::assertCount(2, $layers);
        self::assertSame(MatchSourceKind::Private, $layers[1]->matchSource->kind);
        self::assertSame(CompetitionMatchSelectionMode::All, $layers[1]->selectionMode);
    }

    public function testTheOtherCompetitionOnTheSameCuratedZdrojIsUntouched(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $component = $this->createLiveComponent(
            'Competition:ScopeEditor',
            ['competition' => $this->competition(AppFixtures::SUBSET_COMPETITION_ID)],
            $client,
        );

        $component->call('addOwnMatchesLayer')->call('save');

        $this->testEntityManager()->clear();

        $publicCompetition = $this->competition(AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertCount(1, $publicCompetition->sources);
        self::assertSame(AppFixtures::PUBLIC_SOURCE_ID, $publicCompetition->sources[0]->matchSource->id->toRfc4122());
    }

    // ---- helpers ---------------------------------------------------------

    private function competition(string $id): Competition
    {
        $competition = $this->testEntityManager()->find(Competition::class, Uuid::fromString($id));
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }
}
