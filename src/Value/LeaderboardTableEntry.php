<?php

declare(strict_types=1);

namespace App\Value;

use App\Query\GetCompetitionLeaderboard\LeaderboardRow;

/**
 * One line of the rendered žebříček: either a player row, or the condensed
 * „… pozice 13–24 …" separator standing in for the ranks folded away.
 */
final readonly class LeaderboardTableEntry
{
    private function __construct(
        public bool $isGap,
        public ?LeaderboardRow $row,
        public ?int $gapFrom,
        public ?int $gapTo,
    ) {
    }

    public static function forRow(LeaderboardRow $row): self
    {
        return new self(isGap: false, row: $row, gapFrom: null, gapTo: null);
    }

    public static function forGap(int $from, int $to): self
    {
        return new self(isGap: true, row: null, gapFrom: $from, gapTo: $to);
    }
}
