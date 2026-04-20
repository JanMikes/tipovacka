<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

final readonly class SportMatchImportRow
{
    public function __construct(
        public int $rowNumber,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
    ) {
    }
}
