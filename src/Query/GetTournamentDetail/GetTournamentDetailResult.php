<?php

declare(strict_types=1);

namespace App\Query\GetTournamentDetail;

use App\Enum\TournamentVisibility;
use Symfony\Component\Uid\Uuid;

final readonly class GetTournamentDetailResult
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public ?string $description,
        public TournamentVisibility $visibility,
        public string $sportCode,
        public string $sportName,
        public Uuid $ownerId,
        public string $ownerNickname,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $finishedAt,
    ) {
    }
}
