<?php

declare(strict_types=1);

namespace App\Query\GetMemberLeaderboardBreakdown;

/**
 * One day of a member's snapshot history — the „Vývoj" list on the member
 * breakdown (rank + points as of that Prague day).
 */
final readonly class MemberProgressDay
{
    public function __construct(
        public \DateTimeImmutable $day,
        public int $rank,
        public int $points,
    ) {
    }
}
