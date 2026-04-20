<?php

declare(strict_types=1);

namespace App\Query\GetGroupLeaderboard;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<GroupLeaderboardResult>
 */
final readonly class GetGroupLeaderboard implements QueryMessage
{
    public function __construct(
        public Uuid $groupId,
    ) {
    }
}
