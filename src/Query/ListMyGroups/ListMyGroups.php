<?php

declare(strict_types=1);

namespace App\Query\ListMyGroups;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<GroupListItem>>
 */
final readonly class ListMyGroups implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
