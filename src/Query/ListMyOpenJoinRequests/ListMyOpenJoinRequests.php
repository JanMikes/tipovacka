<?php

declare(strict_types=1);

namespace App\Query\ListMyOpenJoinRequests;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<MyJoinRequestItem>>
 */
final readonly class ListMyOpenJoinRequests implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
