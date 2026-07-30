<?php

declare(strict_types=1);

namespace App\Value;

/**
 * The žebříček as the page renders it: searched and — for a long board —
 * condensed around the viewer instead of paginated.
 */
final readonly class LeaderboardTable
{
    /**
     * @param list<LeaderboardTableEntry> $entries rows and gap separators in render order
     */
    public function __construct(
        public array $entries,
        /** Player rows actually rendered (gaps excluded). */
        public int $shownCount,
        /** Rows the search kept — equals {@see $totalCount} when not searching. */
        public int $matchedCount,
        /** Rows the board holds in total. */
        public int $totalCount,
        /** True when at least one stretch of ranks was folded away. */
        public bool $isCondensed,
    ) {
    }
}
