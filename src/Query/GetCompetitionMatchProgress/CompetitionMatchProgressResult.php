<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionMatchProgress;

final readonly class CompetitionMatchProgressResult
{
    public function __construct(
        /** All of the competition's matches, cancelled ones excluded. */
        public int $matchCount,
        /** How many of them already have a final score. */
        public int $finishedMatchCount,
        /** How many are being played right now. */
        public int $liveMatchCount,
    ) {
    }
}
