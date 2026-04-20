<?php

declare(strict_types=1);

namespace App\Query\GetMemberLeaderboardBreakdown;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<MemberBreakdownResult>
 */
final readonly class GetMemberLeaderboardBreakdown implements QueryMessage
{
    public function __construct(
        public Uuid $groupId,
        public Uuid $userId,
    ) {
    }
}
