<?php

declare(strict_types=1);

namespace App\Query\GetMemberCompetitionStats;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Personal scoreboard for one member within one soutěž (Competition), feeding the
 * dashboard hero stat cards. Derived from the competition leaderboard so the rank /
 * accuracy / exact / streak logic stays in a single place.
 *
 * @implements QueryMessage<MemberCompetitionStatsResult>
 */
final readonly class GetMemberCompetitionStats implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        public Uuid $competitionId,
    ) {
    }
}
