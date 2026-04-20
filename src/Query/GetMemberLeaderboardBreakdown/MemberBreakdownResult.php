<?php

declare(strict_types=1);

namespace App\Query\GetMemberLeaderboardBreakdown;

use Symfony\Component\Uid\Uuid;

final readonly class MemberBreakdownResult
{
    /**
     * @param list<MemberMatchBreakdown> $rows
     */
    public function __construct(
        public Uuid $userId,
        public string $nickname,
        public int $totalPoints,
        public array $rows,
    ) {
    }
}
