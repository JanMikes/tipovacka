<?php

declare(strict_types=1);

namespace App\Query\ListActivePublicTournaments;

use App\Enum\TournamentVisibility;
use Symfony\Component\Uid\Uuid;

final readonly class TournamentListItem
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public TournamentVisibility $visibility,
        public string $ownerNickname,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
        public ?\DateTimeImmutable $finishedAt,
    ) {
    }
}
