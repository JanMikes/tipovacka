<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInGroup;

final readonly class GuessesForMatchInGroupResult
{
    /**
     * @param list<GuessForMatchItem> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
