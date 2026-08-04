<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Entity\MatchSource;
use App\Enum\FeedProvider;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One adapter per FeedProvider case: fetches the current state of every match
 * of the competition a MatchSource is bound to (`feedRef`) and translates it
 * into provider-neutral snapshots. Fetching may do network I/O — it is always
 * called OUTSIDE the sync transaction (app:matches:sync fetches first, then
 * dispatches). Implementations own their vendor→FeedMatchStatus mapping table.
 */
#[AutoconfigureTag('app.match_data_provider')]
interface MatchDataProvider
{
    public static function provides(): FeedProvider;

    /** @return list<MatchSnapshot> */
    public function fetchMatches(MatchSource $source): array;
}
