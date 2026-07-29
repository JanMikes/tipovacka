<?php

declare(strict_types=1);

namespace App\Query\ListBrowsableCompetitions;

use App\Enum\CompetitionBrowseScope;
use App\Enum\CompetitionStateFilter;
use App\Enum\CompetitionVisibilityFilter;
use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * THE list behind both competition grids of the „Soutěže" page (item 07):
 * „Tvé soutěže" (scope {@see CompetitionBrowseScope::Organized}) and the
 * public/global list (scope {@see CompetitionBrowseScope::Discoverable}) —
 * one query, one card, differing only by scope.
 *
 * `$viewerId` is null for anonymous visitors (the organizer scope then yields
 * nothing); when set, each item carries whether the viewer is already an active
 * member, which drives the join / open CTA.
 *
 * The handler issues a CONSTANT number of statements regardless of how long the
 * list is — the per-competition player count and match progress are batch
 * aggregates, never a loop.
 *
 * @implements QueryMessage<BrowsableCompetitionsResult>
 */
final readonly class ListBrowsableCompetitions implements QueryMessage
{
    public const int DEFAULT_PAGE_SIZE = 12;

    public function __construct(
        public CompetitionBrowseScope $scope,
        public ?Uuid $viewerId = null,
        public ?Uuid $sportId = null,
        public CompetitionVisibilityFilter $visibility = CompetitionVisibilityFilter::All,
        public CompetitionStateFilter $state = CompetitionStateFilter::All,
        /** Free-text needle matched against the competition and its zdroj zápasů name. */
        public ?string $search = null,
        public int $page = 1,
        public int $pageSize = self::DEFAULT_PAGE_SIZE,
    ) {
    }
}
