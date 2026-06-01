<?php

declare(strict_types=1);

namespace App\Query\GetMemberGroupStats;

use App\Query\GetGroupLeaderboard\GetGroupLeaderboard;
use App\Query\QueryBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMemberGroupStatsQuery
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    public function __invoke(GetMemberGroupStats $query): MemberGroupStatsResult
    {
        $leaderboard = $this->queryBus->handle(new GetGroupLeaderboard(groupId: $query->groupId));
        $totalMembers = count($leaderboard->rows);

        foreach ($leaderboard->rows as $row) {
            if ($row->userId->equals($query->userId)) {
                return new MemberGroupStatsResult(
                    rank: $row->rank,
                    totalMembers: $totalMembers,
                    totalPoints: $row->totalPoints,
                    evaluatedCount: $row->evaluatedCount,
                    scoredCount: $row->scoredCount,
                    exactCount: $row->exactCount,
                    partialCount: $row->partialCount,
                    accuracyPercent: $row->accuracyPercent,
                    streak: $row->streak,
                    isMember: true,
                );
            }
        }

        return new MemberGroupStatsResult(
            rank: 0,
            totalMembers: $totalMembers,
            totalPoints: 0,
            evaluatedCount: 0,
            scoredCount: 0,
            exactCount: 0,
            partialCount: 0,
            accuracyPercent: 0,
            streak: 0,
            isMember: false,
        );
    }
}
