<?php

declare(strict_types=1);

namespace App\Command\CreateSportMatch;

use Symfony\Component\Uid\Uuid;

final readonly class CreateSportMatchCommand
{
    public function __construct(
        public Uuid $tournamentId,
        public Uuid $editorId,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
    ) {
    }
}
