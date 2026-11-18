<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

/** One played match without a result, as the daily check reports and mails it. */
final class MissingResult
{
    public function __construct(
        public readonly string $sourceName,
        public readonly string $homeTeam,
        public readonly string $awayTeam,
        public readonly \DateTimeImmutable $kickoffAt,
        public readonly bool $feedBound,
        public readonly int $tips,
        public readonly string $url,
    ) {
    }

    public string $label {
        get => sprintf('%s: %s – %s (%s UTC)', $this->sourceName, $this->homeTeam, $this->awayTeam, $this->kickoffAt->format('Y-m-d H:i'));
    }
}
