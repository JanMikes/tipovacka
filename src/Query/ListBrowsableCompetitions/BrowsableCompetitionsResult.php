<?php

declare(strict_types=1);

namespace App\Query\ListBrowsableCompetitions;

/* Not a `readonly class`: {@see $hasMore} is a hooked virtual property, and
   hooked properties may not be readonly — the data is immutable per-property. */
final class BrowsableCompetitionsResult
{
    /**
     * @param list<BrowsableCompetitionItem> $items         the requested page, filters applied
     * @param list<SportFilterOption>        $sportOptions  sports actually present in the unfiltered scope
     * @param int                            $filteredCount how many competitions the active filters keep
     * @param int                            $totalCount    how many the scope holds in total („X z Y soutěží")
     */
    public function __construct(
        public readonly array $items,
        public readonly array $sportOptions,
        public readonly int $filteredCount,
        public readonly int $totalCount,
        public readonly int $page,
        public readonly int $pageCount,
    ) {
    }

    public bool $hasMore {
        get => $this->page < $this->pageCount;
    }
}
