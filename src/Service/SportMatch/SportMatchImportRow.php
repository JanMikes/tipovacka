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
        public ?string $round = null,
        public bool $isPlayoff = false,
        // Preview-only: this name isn't a team yet in the source's scope, so importing
        // will create it. First occurrence of a repeated new name carries the flag.
        public bool $homeTeamIsNew = false,
        public bool $awayTeamIsNew = false,
    ) {
    }
}
