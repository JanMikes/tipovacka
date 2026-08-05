<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionSource;
use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionMatchSelectionFlowTest extends WebTestCase
{
    public function testOwnerSeesGroupedCheckboxListWithCurrentSelection(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Výběr zápasů');
        self::assertSelectorTextContains('body', 'Sparta Praha');
        self::assertSelectorTextContains('body', 'Playoff');

        // Fixture selection: MATCH_SCHEDULED + MATCH_FINISHED are checked.
        $checked = $crawler->filter('input[name="matches[]"]:checked')->each(
            static fn ($node) => $node->attr('value'),
        );
        sort($checked);
        $expected = [AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_FINISHED_ID];
        sort($expected);
        self::assertSame($expected, $checked);
    }

    /**
     * A soutěž hand-picking from two zdroje edits one layer at a time. Saving
     * one must leave the other's picks untouched — a full-replace scoped to the
     * whole competition would wipe them.
     */
    public function testEditingOneLayerLeavesTheOtherLayersPicksAlone(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $secondLayer = $this->addPrivateSubsetLayer($em);

        // Edit the FIRST layer (the public zdroj), replacing its picks entirely.
        $crawler = $client->request('GET', '/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber?vrstva='.AppFixtures::SUBSET_COMPETITION_SOURCE_ID);
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        self::assertIsString($token);

        $client->request('POST', '/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber?vrstva='.AppFixtures::SUBSET_COMPETITION_SOURCE_ID, [
            '_token' => $token,
            'matches' => [AppFixtures::MATCH_PLAYOFF_ID],
        ]);

        $em->clear();

        $byLayer = [];

        foreach ($em->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
            ->getQuery()
            ->getResult() as $selection) {
            $byLayer[$selection->competitionSource->id->toRfc4122()][] = $selection->sportMatch->id->toRfc4122();
        }

        self::assertSame([AppFixtures::MATCH_PLAYOFF_ID], $byLayer[AppFixtures::SUBSET_COMPETITION_SOURCE_ID] ?? []);
        self::assertSame([AppFixtures::MATCH_PRIVATE_SCHEDULED_ID], $byLayer[$secondLayer] ?? []);
    }

    /** Both zdroje are offered as tabs once the soutěž draws from two. */
    public function testTheLayerSwitcherListsEveryManageableZdroj(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $this->addPrivateSubsetLayer($em);

        $client->request('GET', '/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber');
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('vrstva='.AppFixtures::SUBSET_COMPETITION_SOURCE_ID, $html);
        self::assertStringContainsString(AppFixtures::PRIVATE_SOURCE_NAME, $html);
    }

    /**
     * Gives SUBSET_COMPETITION a second subset layer over the private zdroj,
     * holding exactly MATCH_PRIVATE_SCHEDULED.
     *
     * @return string the new layer's UUID
     */
    private function addPrivateSubsetLayer(EntityManagerInterface $em): string
    {
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        $privateSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $privateSource);
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $layer = new CompetitionSource(
            id: Uuid::v7(),
            competition: $competition,
            matchSource: $privateSource,
            addedAt: $now,
            selectionMode: CompetitionMatchSelectionMode::Subset,
            position: 1,
        );
        $competition->attachSource($layer);
        $em->persist($layer);
        $em->persist(new CompetitionMatchSelection(
            id: Uuid::v7(),
            competition: $competition,
            competitionSource: $layer,
            sportMatch: $match,
            addedAt: $now,
        ));
        $em->flush();

        return $layer->id->toRfc4122();
    }

    public function testOwnerCanReplaceSelection(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        self::assertIsString($token);

        $client->request('POST', '/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber', [
            '_token' => $token,
            'matches' => [AppFixtures::MATCH_PLAYOFF_ID],
        ]);

        // Back to the SAME layer that was just edited — a soutěž drawing from
        // several zdroje must not bounce the manager to a different one.
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertStringStartsWith('/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber?vrstva=', $location);
        self::assertStringContainsString(AppFixtures::SUBSET_COMPETITION_SOURCE_ID, $location);

        $em->clear();

        $selectedIds = array_map(
            static fn (CompetitionMatchSelection $s): string => $s->sportMatch->id->toRfc4122(),
            $em->createQueryBuilder()
                ->select('s')
                ->from(CompetitionMatchSelection::class, 's')
                ->where('s.competition = :competitionId')
                ->setParameter('competitionId', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
                ->getQuery()
                ->getResult(),
        );

        self::assertSame([AppFixtures::MATCH_PLAYOFF_ID], $selectedIds);
    }

    public function testNonOwnerMemberCannotManageSelection(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $stranger = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($stranger);
        $client->loginUser($stranger);

        $client->request('GET', '/souteze/'.AppFixtures::SUBSET_COMPETITION_ID.'/zapasy-vyber');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAllModeCompetitionRedirectsToSettings(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/zapasy-vyber');
        self::assertResponseRedirects('/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/nastaveni');
    }
}
