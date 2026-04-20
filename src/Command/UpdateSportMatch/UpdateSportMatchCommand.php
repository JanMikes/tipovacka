<?php

declare(strict_types=1);

namespace App\Command\UpdateSportMatch;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateSportMatchCommand
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $editorId,
        public ?string $homeTeam,
        public ?string $awayTeam,
        public ?\DateTimeImmutable $kickoffAt,
        public ?string $venue,
    ) {
    }
}
