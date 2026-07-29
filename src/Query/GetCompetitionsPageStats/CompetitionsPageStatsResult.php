<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionsPageStats;

final readonly class CompetitionsPageStatsResult
{
    public function __construct(
        /** Competitions in scope whose match source is still running. */
        public int $activeCompetitionCount,
        /** Of those, how many have a match in play right now. */
        public int $liveCompetitionCount,
        /** Matches in scope kicking off on today's Prague calendar day. */
        public int $todayMatchCount,
        /** Distinct people with an active membership anywhere in scope. */
        public int $playerCount,
        /** How many of them joined within the last seven days. */
        public int $newPlayerCount,
        /** Matches the scope's competitions actually include. */
        public int $matchCount,
        /** Across how many zdroje zápasů („turnaje") those matches are spread. */
        public int $matchSourceCount,
    ) {
    }
}
