<?php

declare(strict_types=1);

namespace App\Service\Leaderboard;

use App\Query\GetCompetitionLeaderboard\LeaderboardRow;
use App\Value\LeaderboardTable;
use App\Value\LeaderboardTableEntry;

/**
 * Turns a leaderboard result into the lines the Žebříček page renders: the search
 * needle and — the interesting part — the condensed view.
 *
 * A long board is NOT paginated. Paging hides the viewer's own row behind a page
 * button; instead the ranks between the head of the table, the viewer's own
 * neighbourhood and the tail are folded into a single „… pozice 13–24 …"
 * separator, so „where am I and who is around me" is answerable at a glance.
 * Searching turns the fold off (it breaks the rank contiguity the fold depends
 * on) — since item 15 the search is the page's ONE surviving control, so it is
 * also how a viewer reaches somebody hidden inside a folded stretch.
 */
final readonly class LeaderboardTableBuilder
{
    /** Ranks always shown at the top. */
    private const int HEAD_SIZE = 12;

    /** Rows kept on each side of the viewer's own row. */
    private const int AROUND_ME = 2;

    /** Ranks always shown at the bottom. */
    private const int TAIL_SIZE = 2;

    /**
     * Fold only when it actually saves lines — a board barely over the head size
     * would trade three rows for a separator, which reads worse than the rows.
     */
    private const int MIN_FOLDED_RANKS = 3;

    /**
     * @param list<LeaderboardRow> $rows
     */
    public function build(
        array $rows,
        string $search,
        ?LeaderboardRow $meRow,
    ): LeaderboardTable {
        $totalCount = count($rows);

        $matched = '' === $search ? $rows : $this->filter($rows, $search);
        $matchedCount = count($matched);

        $ordered = $matched;

        $keep = '' === $search
            ? $this->visibleIndexes($ordered, $meRow)
            : null;

        $entries = [];
        $shownCount = 0;
        $pendingGap = [];

        foreach ($ordered as $index => $row) {
            if (null !== $keep && !isset($keep[$index])) {
                $pendingGap[] = $row->rank;

                continue;
            }

            if ([] !== $pendingGap) {
                $entries[] = LeaderboardTableEntry::forGap($pendingGap[0], $pendingGap[count($pendingGap) - 1]);
                $pendingGap = [];
            }

            $entries[] = LeaderboardTableEntry::forRow($row);
            ++$shownCount;
        }

        if ([] !== $pendingGap) {
            $entries[] = LeaderboardTableEntry::forGap($pendingGap[0], $pendingGap[count($pendingGap) - 1]);
        }

        return new LeaderboardTable(
            entries: $entries,
            shownCount: $shownCount,
            matchedCount: $matchedCount,
            totalCount: $totalCount,
            isCondensed: $shownCount < $matchedCount,
        );
    }

    /**
     * @param list<LeaderboardRow> $rows
     *
     * @return list<LeaderboardRow>
     */
    private function filter(array $rows, string $search): array
    {
        return array_values(array_filter(
            $rows,
            static fn (LeaderboardRow $row): bool => false !== mb_stripos($row->nickname, $search)
                || (null !== $row->fullName && false !== mb_stripos($row->fullName, $search)),
        ));
    }

    /**
     * Indexes that survive the fold: the head, the viewer's neighbourhood and the
     * tail. Returns null when nothing worth folding is left.
     *
     * @param list<LeaderboardRow> $rows
     *
     * @return array<int, true>|null
     */
    private function visibleIndexes(array $rows, ?LeaderboardRow $meRow): ?array
    {
        $total = count($rows);

        if ($total <= self::HEAD_SIZE + self::TAIL_SIZE + self::MIN_FOLDED_RANKS) {
            return null;
        }

        $keep = [];

        for ($i = 0; $i < self::HEAD_SIZE; ++$i) {
            $keep[$i] = true;
        }

        for ($i = max(0, $total - self::TAIL_SIZE); $i < $total; ++$i) {
            $keep[$i] = true;
        }

        if (null !== $meRow) {
            foreach ($rows as $index => $row) {
                if (!$row->userId->equals($meRow->userId)) {
                    continue;
                }

                for ($i = max(0, $index - self::AROUND_ME); $i <= min($total - 1, $index + self::AROUND_ME); ++$i) {
                    $keep[$i] = true;
                }

                break;
            }
        }

        if (count($keep) >= $total - self::MIN_FOLDED_RANKS + 1) {
            return null;
        }

        return $keep;
    }
}
