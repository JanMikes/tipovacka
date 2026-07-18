<?php

declare(strict_types=1);

namespace App\Query\ListDiscoverablePublicMatchSources;

use Symfony\Component\Uid\Uuid;

final readonly class DiscoverableMatchSourceItem
{
    public function __construct(
        public Uuid $matchSourceId,
        public string $name,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
        public int $competitionCount,
        public int $memberCount,
    ) {
    }
}
