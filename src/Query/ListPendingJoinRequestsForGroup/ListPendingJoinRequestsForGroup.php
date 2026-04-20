<?php

declare(strict_types=1);

namespace App\Query\ListPendingJoinRequestsForGroup;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<JoinRequestListItem>>
 */
final readonly class ListPendingJoinRequestsForGroup implements QueryMessage
{
    public function __construct(
        public Uuid $groupId,
    ) {
    }
}
