<?php

declare(strict_types=1);

namespace App\Query\ListUpcomingMatchesForUser;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<UpcomingMatchItem>>
 */
final readonly class ListUpcomingMatchesForUser implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
