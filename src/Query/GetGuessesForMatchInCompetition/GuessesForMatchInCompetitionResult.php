<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInCompetition;

final readonly class GuessesForMatchInCompetitionResult
{
    /**
     * @param list<GuessForMatchItem> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
