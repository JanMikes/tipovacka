<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

final class MissingResultsReport
{
    /**
     * @param list<MissingResult> $curated       ours to fix — an incident
     * @param list<MissingResult> $privateRecent the organizer's, last week only — information
     */
    public function __construct(
        public readonly array $curated,
        public readonly array $privateRecent,
    ) {
    }

    public bool $isEmpty {
        get => [] === $this->curated && [] === $this->privateRecent;
    }
}
