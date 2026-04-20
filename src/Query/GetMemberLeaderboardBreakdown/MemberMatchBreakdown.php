<?php

declare(strict_types=1);

namespace App\Query\GetMemberLeaderboardBreakdown;

use Symfony\Component\Uid\Uuid;

final readonly class MemberMatchBreakdown
{
    /**
     * @param list<RulePointsItem> $breakdown
     */
    public function __construct(
        public Uuid $sportMatchId,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?int $actualHomeScore,
        public ?int $actualAwayScore,
        public ?int $myHomeScore,
        public ?int $myAwayScore,
        public int $totalPoints,
        public array $breakdown,
    ) {
    }
}
