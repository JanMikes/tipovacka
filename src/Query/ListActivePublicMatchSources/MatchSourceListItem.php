<?php

declare(strict_types=1);

namespace App\Query\ListActivePublicMatchSources;

use App\Enum\MatchSourceKind;
use Symfony\Component\Uid\Uuid;

final readonly class MatchSourceListItem
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public MatchSourceKind $kind,
        public string $ownerNickname,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
        public ?\DateTimeImmutable $finishedAt,
    ) {
    }
}
