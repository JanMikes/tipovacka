<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Enum\CompetitionBrowseScope;
use App\Enum\CompetitionStateFilter;
use App\Enum\CompetitionVisibilityFilter;
use App\Query\ListBrowsableCompetitions\BrowsableCompetitionItem;
use App\Query\ListBrowsableCompetitions\ListBrowsableCompetitions;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\Uid\Uuid;

/**
 * The one list behind both competition grids of `/souteze` (item 07).
 */
final class ListBrowsableCompetitionsQueryTest extends IntegrationTestCase
{
    public function testDiscoverableScopeListsOnlyGlobalCompetitionsOnRunningSources(): void
    {
        $result = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
        ));

        self::assertSame(
            [AppFixtures::GLOBAL_COMPETITION_NAME, AppFixtures::FREE_GLOBAL_COMPETITION_NAME],
            $this->namesOf($result->items),
        );
        self::assertSame(2, $result->totalCount);
        self::assertSame(2, $result->filteredCount);
        self::assertFalse($result->hasMore);
    }

    public function testCompletedSourceDropsItsGlobalCompetitionsFromDiscovery(): void
    {
        $this->commandBus()->dispatch(new MarkMatchSourceCompletedCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
        ));

        $result = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
        ));

        self::assertSame([], $this->namesOf($result->items));
    }

    public function testOrganizedScopeIsOwnerScopedAndEmptyWithoutAViewer(): void
    {
        $mine = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Organized,
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertSame([AppFixtures::VERIFIED_COMPETITION_NAME], $this->namesOf($mine->items));
        self::assertTrue($mine->items[0]->viewerIsOwner);

        $anonymous = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Organized,
        ));

        self::assertSame([], $anonymous->items);
        self::assertSame(0, $anonymous->totalCount);
    }

    public function testViewerMembershipDrivesTheJoinOrOpenCta(): void
    {
        $result = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            viewerId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        // ADMIN owns and is the sole member of both global competitions.
        foreach ($result->items as $item) {
            self::assertTrue($item->viewerIsMember, $item->name);
            self::assertSame(1, $item->playerCount, $item->name);
        }
    }

    public function testMatchAggregatesRespectTheCompetitionMatchScope(): void
    {
        // SUBSET_COMPETITION includes exactly two of the curated source's matches.
        $result = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Organized,
            viewerId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
        ));

        self::assertSame([AppFixtures::SUBSET_COMPETITION_NAME], $this->namesOf($result->items));
        self::assertSame(2, $result->items[0]->matchCount);
        self::assertSame(1, $result->items[0]->finishedMatchCount);
        self::assertSame(50, $result->items[0]->progressPercent);
    }

    public function testSportFilterAndOptionsDescribeTheUnfilteredScope(): void
    {
        $unfiltered = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
        ));
        self::assertSame(['Fotbal'], array_map(static fn ($o): string => $o->name, $unfiltered->sportOptions));

        $hockey = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            sportId: Uuid::fromString(Sport::HOCKEY_ID),
        ));

        self::assertSame([], $hockey->items);
        self::assertSame(0, $hockey->filteredCount);
        // The total keeps describing the scope, so the UI can say „0 z 2".
        self::assertSame(2, $hockey->totalCount);
        self::assertSame(['Fotbal'], array_map(static fn ($o): string => $o->name, $hockey->sportOptions));
    }

    public function testVisibilityAndStateFilters(): void
    {
        $publicOnly = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Organized,
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            visibility: CompetitionVisibilityFilter::Public,
        ));
        self::assertSame([], $publicOnly->items, '„Kámoši u piva" is not a global competition.');

        $privateOnly = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Organized,
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            visibility: CompetitionVisibilityFilter::Private,
        ));
        self::assertSame([AppFixtures::VERIFIED_COMPETITION_NAME], $this->namesOf($privateOnly->items));

        // Its only match kicks off after the fixed clock ⇒ nothing started yet.
        self::assertSame(CompetitionStateFilter::Upcoming, $privateOnly->items[0]->state);

        $finished = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Organized,
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            state: CompetitionStateFilter::Finished,
        ));
        self::assertSame([], $finished->items);
    }

    public function testSearchMatchesTheCompetitionAndTheSourceName(): void
    {
        $byCompetition = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            search: 'ZDARMA',
        ));
        self::assertSame([AppFixtures::FREE_GLOBAL_COMPETITION_NAME], $this->namesOf($byCompetition->items));

        $bySource = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            search: 'Liga mistrů',
        ));
        self::assertCount(2, $bySource->items);
    }

    public function testPaginationSlicesTheFilteredResult(): void
    {
        $firstPage = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            pageSize: 1,
        ));

        self::assertCount(1, $firstPage->items);
        self::assertSame(2, $firstPage->pageCount);
        self::assertTrue($firstPage->hasMore);

        $secondPage = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            pageSize: 1,
            page: 2,
        ));

        self::assertCount(1, $secondPage->items);
        self::assertFalse($secondPage->hasMore);
        self::assertNotSame($firstPage->items[0]->name, $secondPage->items[0]->name);

        // An out-of-range page clamps instead of 404-ing on a shared link.
        $overshoot = $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            pageSize: 1,
            page: 99,
        ));
        self::assertSame(2, $overshoot->page);
    }

    /**
     * The acceptance criterion the predecessor failed: it ran a COUNT per row
     * inside its loop, so the list cost grew with the list. Listing four
     * competitions must cost exactly as much as listing one.
     */
    public function testStatementCountDoesNotGrowWithTheListLength(): void
    {
        $short = $this->statementsFor(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        // Every competition in the fixtures, organized or not — a longer list over
        // more sources, resolved by the same statements.
        $long = $this->statementsFor(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Organized,
            viewerId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        self::assertGreaterThan(2, $this->queryBus()->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Organized,
            viewerId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ))->totalCount, 'The long list must really be longer, or the assertion proves nothing.');

        self::assertSame($short, $long);
    }

    private function statementsFor(ListBrowsableCompetitions $query): int
    {
        $before = $this->executedStatements();
        $this->queryBus()->handle($query);

        return $this->executedStatements() - $before;
    }

    private function executedStatements(): int
    {
        /** @var DebugDataHolder $holder */
        $holder = self::getContainer()->get('doctrine.debug_data_holder');

        $count = 0;

        foreach ($holder->getData() as $queries) {
            $count += count($queries);
        }

        return $count;
    }

    /**
     * @param list<BrowsableCompetitionItem> $items
     *
     * @return list<string>
     */
    private function namesOf(array $items): array
    {
        $names = array_map(static fn (BrowsableCompetitionItem $item): string => $item->name, $items);
        sort($names);

        return $names;
    }
}
