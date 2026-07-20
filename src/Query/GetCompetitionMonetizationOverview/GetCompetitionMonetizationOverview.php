<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionMonetizationOverview;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Read-only premium/boost state of a competition for the admin management page:
 * the premium per-player charges (incl. uncovered ones, which have no ledger row)
 * and the active boost purchases.
 *
 * @implements QueryMessage<CompetitionMonetizationOverviewResult>
 */
final readonly class GetCompetitionMonetizationOverview implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
    ) {
    }
}
