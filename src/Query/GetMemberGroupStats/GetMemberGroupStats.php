<?php

declare(strict_types=1);

namespace App\Query\GetMemberGroupStats;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Personal scoreboard for one member within one soutěž (Group), feeding the
 * dashboard hero stat cards. Derived from the group leaderboard so the rank /
 * accuracy / exact / streak logic stays in a single place.
 *
 * @implements QueryMessage<MemberGroupStatsResult>
 */
final readonly class GetMemberGroupStats implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        public Uuid $groupId,
    ) {
    }
}
