<?php

declare(strict_types=1);

namespace App\Command\CreatePrivateTournament;

use Symfony\Component\Uid\Uuid;

final readonly class CreatePrivateTournamentCommand
{
    public function __construct(
        public Uuid $ownerId,
        public string $name,
        public ?string $description,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
    ) {
    }
}
