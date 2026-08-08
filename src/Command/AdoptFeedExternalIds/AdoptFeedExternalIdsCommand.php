<?php

declare(strict_types=1);

namespace App\Command\AdoptFeedExternalIds;

use App\Service\Feed\ExternalIdAdopter;
use App\Service\Feed\MatchSnapshot;
use Symfony\Component\Uid\Uuid;

/**
 * Stamp a source's existing matches with their provider's identifiers, in one
 * transaction. Fetching happens BEFORE dispatch (see the console command) so
 * provider network I/O never runs inside the DB transaction.
 */
final readonly class AdoptFeedExternalIdsCommand
{
    /**
     * @param list<MatchSnapshot> $snapshots
     */
    public function __construct(
        public Uuid $matchSourceId,
        public array $snapshots,
        public int $kickoffToleranceHours = ExternalIdAdopter::DEFAULT_KICKOFF_TOLERANCE_HOURS,
    ) {
    }
}
