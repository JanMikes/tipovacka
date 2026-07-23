<?php

declare(strict_types=1);

namespace App\Query\GetPickDistributions;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Batch form of {@see \App\Query\GetMatchPickDistribution\GetMatchPickDistribution}
 * — the 1 / X / 2 split for MANY (competition, match) pairs in a single query.
 *
 * Every screen that lists matches shows the distribution (or its paywall), so the
 * per-match query would be a textbook N+1. Pass the full cross product of the
 * competitions and matches on the page; the handler groups server-side and only
 * the requested pairs come back.
 *
 * @implements QueryMessage<PickDistributions>
 */
final readonly class GetPickDistributions implements QueryMessage
{
    /**
     * @param list<Uuid> $competitionIds
     * @param list<Uuid> $sportMatchIds
     */
    public function __construct(
        public array $competitionIds,
        public array $sportMatchIds,
    ) {
    }
}
