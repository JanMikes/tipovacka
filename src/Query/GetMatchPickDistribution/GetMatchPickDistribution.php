<?php

declare(strict_types=1);

namespace App\Query\GetMatchPickDistribution;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Distribution of a match's tips within a group, bucketed by outcome (1 / X / 2).
 *
 * @implements QueryMessage<MatchPickDistributionResult>
 */
final readonly class GetMatchPickDistribution implements QueryMessage
{
    public function __construct(
        public Uuid $groupId,
        public Uuid $sportMatchId,
    ) {
    }
}
