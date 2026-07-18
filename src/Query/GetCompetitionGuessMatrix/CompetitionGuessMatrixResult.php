<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionGuessMatrix;

final readonly class CompetitionGuessMatrixResult
{
    /**
     * @param list<MatrixMatchColumn> $matches
     * @param list<MatrixMemberRow>   $members
     */
    public function __construct(
        public array $matches,
        public array $members,
    ) {
    }
}
