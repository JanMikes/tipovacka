<?php

declare(strict_types=1);

namespace App\Service\Leaderboard;

use App\Enum\LeaderboardSort;
use App\Query\GetCompetitionLeaderboard\LeaderboardRow;
use App\Value\LeaderboardTable;
use App\Value\LeaderboardTableEntry;

/**
 * Turns a leaderboard result into the lines the Žebříček page renders: the search
 * needle, the „Seřadit" order and — the interesting part — the condensed view.
 *
 * A long board is NOT paginated. Paging hides the viewer's own row behind a page
 * button; instead the ranks between the head of the table, the viewer's own
 * neighbourhood and the tail are folded into a single „… pozice 13–24 …"
 * separator, so „where am I and who is around me" is answerable at a glance.
 * `?vse=1` expands it, and searching or re-sorting turns it off (both break the
 * rank-contiguity the fold depends on).
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
        string $sort,
        ?LeaderboardRow $meRow,
        bool $expanded,
    ): LeaderboardTable {
        $sortOrder = LeaderboardSort::fromRequest($sort);
        $totalCount = count($rows);

        $matched = '' === $search ? $rows : $this->filter($rows, $search);
        $matchedCount = count($matched);

        $ordered = $this->sort($matched, $sortOrder);

        $isNaturalOrder = LeaderboardSort::Points === $sortOrder && '' === $search;
        $keep = $isNaturalOrder && !$expanded
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
            sort: $sortOrder,
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
     * Rank stays the row's own; only the display order changes. Every secondary
     * key falls back to points and then to rank, so the order is total (a stable
     * page across reloads).
     *
     * @param list<LeaderboardRow> $rows
     *
     * @return list<LeaderboardRow>
     */
    private function sort(array $rows, LeaderboardSort $sort): array
    {
        if (LeaderboardSort::Points === $sort) {
            return $rows;
        }

        usort($rows, static function (LeaderboardRow $a, LeaderboardRow $b) use ($sort): int {
            // Points is short-circuited above, so it never reaches this match.
            $primary = match ($sort) {
                LeaderboardSort::Accuracy => $b->accuracyPercent <=> $a->accuracyPercent,
                LeaderboardSort::Exact => $b->exactCount <=> $a->exactCount,
                default => $b->streak <=> $a->streak,
            };

            return $primary ?: ($b->totalPoints <=> $a->totalPoints ?: $a->rank <=> $b->rank);
        });

        return $rows;
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
