<?php

declare(strict_types=1);

namespace App\Query\ListPendingJoinRequestsForCompetition;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<JoinRequestListItem>>
 */
final readonly class ListPendingJoinRequestsForCompetition implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
    ) {
    }
}
