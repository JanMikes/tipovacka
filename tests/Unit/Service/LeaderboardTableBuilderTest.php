<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Query\GetCompetitionLeaderboard\LeaderboardRow;
use App\Service\Leaderboard\LeaderboardTableBuilder;
use App\Value\LeaderboardTable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The „… pozice 13–24 …" fold, the search and the „Seřadit" order — item 05.
 */
final class LeaderboardTableBuilderTest extends TestCase
{
    public function testAShortBoardIsRenderedWhole(): void
    {
        $rows = $this->rows(10);

        $table = $this->build($rows);

        self::assertSame(10, $table->shownCount);
        self::assertFalse($table->isCondensed);
        self::assertCount(10, $table->entries);
    }

    public function testALongBoardFoldsTheMiddleAndKeepsHeadAndTail(): void
    {
        $rows = $this->rows(40);

        $table = $this->build($rows);

        self::assertTrue($table->isCondensed);
        self::assertSame(40, $table->totalCount);
        // Head (1–12) + tail (39–40) + one separator.
        self::assertSame(14, $table->shownCount);
        $ranks = $this->ranksOf($table);
        self::assertSame([1, 12, 39, 40], [$ranks[0], $ranks[11], $ranks[12], $ranks[13]]);
        self::assertSame([[13, 38]], $this->gapsOf($table));
    }

    public function testTheViewersOwnNeighbourhoodSurvivesTheFold(): void
    {
        $rows = $this->rows(40);
        $me = $rows[24]; // rank 25

        $table = $this->build($rows, meRow: $me);

        $ranks = $this->ranksOf($table);

        self::assertContains(25, $ranks, 'The viewer is always on the page.');
        self::assertContains(23, $ranks);
        self::assertContains(27, $ranks);
        self::assertNotContains(20, $ranks);
        // Two folded stretches now: 13–22 and 28–38.
        self::assertCount(2, $this->gapsOf($table));
    }

    public function testExpandingShowsEveryRank(): void
    {
        $table = $this->build($this->rows(40), expanded: true);

        self::assertFalse($table->isCondensed);
        self::assertSame(40, $table->shownCount);
    }

    public function testSearchingNarrowsTheBoardAndTurnsTheFoldOff(): void
    {
        $rows = $this->rows(40);

        $table = $this->build($rows, search: 'hráč 3');

        // „hráč 3", „hráč 30".."hráč 39" — matched by substring, no fold.
        self::assertFalse($table->isCondensed);
        self::assertSame(11, $table->matchedCount);
        self::assertSame(11, $table->shownCount);
        self::assertSame(40, $table->totalCount);
    }

    public function testSearchIsCaseInsensitiveAndAlsoMatchesTheFullName(): void
    {
        $rows = [
            $this->row(1, 'jarda', fullName: 'Jaroslav Beran'),
            $this->row(2, 'peta', fullName: 'Petr Volný'),
        ];

        self::assertSame(1, $this->build($rows, search: 'JARDA')->matchedCount);
        self::assertSame(1, $this->build($rows, search: 'Volný')->matchedCount);
        self::assertSame(0, $this->build($rows, search: 'nikdo')->matchedCount);
    }

    public function testSortingReordersRowsButNeverRewritesTheirRank(): void
    {
        $rows = [
            $this->row(1, 'a', points: 30, streak: 0),
            $this->row(2, 'b', points: 20, streak: 7),
            $this->row(3, 'c', points: 10, streak: 3),
        ];

        $table = $this->build($rows, sort: 'streak');

        self::assertSame([2, 3, 1], $this->ranksOf($table));
    }

    public function testAnUnknownSortFallsBackToPoints(): void
    {
        $table = $this->build($this->rows(3), sort: 'nesmysl');

        self::assertSame([1, 2, 3], $this->ranksOf($table));
    }

    /**
     * @param list<LeaderboardRow> $rows
     */
    private function build(
        array $rows,
        string $search = '',
        string $sort = 'body',
        ?LeaderboardRow $meRow = null,
        bool $expanded = false,
    ): LeaderboardTable {
        return (new LeaderboardTableBuilder())->build(
            rows: $rows,
            search: $search,
            sort: $sort,
            meRow: $meRow,
            expanded: $expanded,
        );
    }

    /**
     * @return list<int>
     */
    private function ranksOf(LeaderboardTable $table): array
    {
        $ranks = [];

        foreach ($table->entries as $entry) {
            if (null !== $entry->row) {
                $ranks[] = $entry->row->rank;
            }
        }

        return $ranks;
    }

    /**
     * @return list<array{int, int}>
     */
    private function gapsOf(LeaderboardTable $table): array
    {
        $gaps = [];

        foreach ($table->entries as $entry) {
            if (null !== $entry->gapFrom && null !== $entry->gapTo) {
                $gaps[] = [$entry->gapFrom, $entry->gapTo];
            }
        }

        return $gaps;
    }

    /**
     * @return list<LeaderboardRow>
     */
    private function rows(int $count): array
    {
        $rows = [];

        for ($i = 1; $i <= $count; ++$i) {
            $rows[] = $this->row($i, 'hráč '.$i, points: 1000 - $i);
        }

        return $rows;
    }

    private function row(
        int $rank,
        string $nickname,
        ?string $fullName = null,
        int $points = 0,
        int $streak = 0,
    ): LeaderboardRow {
        return new LeaderboardRow(
            userId: Uuid::v7(),
            nickname: $nickname,
            fullName: $fullName,
            totalPoints: $points,
            rank: $rank,
            isTieResolvedOverride: false,
            streak: $streak,
        );
    }
}
