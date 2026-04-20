<?php

declare(strict_types=1);

namespace App\Command\UpdateTournament;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateTournamentCommand
{
    public function __construct(
        public Uuid $tournamentId,
        public string $name,
        public ?string $description,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
    ) {
    }
}
