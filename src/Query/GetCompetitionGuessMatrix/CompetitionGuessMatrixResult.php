<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionGuessMatrix;

final readonly class CompetitionGuessMatrixResult
{
    /**
     * @param list<MatrixMatchColumn> $matches
     * @param list<MatrixMemberRow>   $members
     * @param int                     $hiddenMatchCount how many columns withhold others' tips from this
     *                                                  viewer (item 20). Non-zero ⇒ the viewer holds no
     *                                                  entitlement and the page owes them the unlock CTA.
     */
    public function __construct(
        public array $matches,
        public array $members,
        public int $hiddenMatchCount = 0,
    ) {
    }
}
