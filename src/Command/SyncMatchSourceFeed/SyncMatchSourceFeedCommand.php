<?php

declare(strict_types=1);

namespace App\Command\SyncMatchSourceFeed;

use App\Service\Feed\MatchSnapshot;
use Symfony\Component\Uid\Uuid;

/**
 * Apply one already-fetched batch of feed snapshots to one source, in one
 * transaction. Fetching happens BEFORE dispatch (app:matches:sync) so provider
 * network I/O never runs inside the DB transaction.
 */
final readonly class SyncMatchSourceFeedCommand
{
    /**
     * @param list<MatchSnapshot> $snapshots
     */
    public function __construct(
        public Uuid $matchSourceId,
        public array $snapshots,
    ) {
    }
}
