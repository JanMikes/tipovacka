<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionCurrentRound;

final readonly class CompetitionCurrentRoundResult
{
    public function __construct(
        /**
         * The round label, or null when the competition has no round-labelled
         * match at all — round-scoped UI (hero stat, „Poslední kolo" tab) must
         * then be hidden rather than shown empty.
         */
        public ?string $round,
        /** Matches of this competition in that round (0 when $round is null). */
        public int $matchCount,
        /** How many of those are already finished. */
        public int $finishedMatchCount,
    ) {
    }
}
