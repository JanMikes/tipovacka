<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionLeaderboard;

use App\Enum\LeaderboardTimeFilter;
use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<CompetitionLeaderboardResult>
 */
final readonly class GetCompetitionLeaderboard implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
        public LeaderboardTimeFilter $filter = LeaderboardTimeFilter::AllTime,
    ) {
    }
}
