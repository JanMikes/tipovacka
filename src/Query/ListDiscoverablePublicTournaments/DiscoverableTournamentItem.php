<?php

declare(strict_types=1);

namespace App\Query\ListDiscoverablePublicTournaments;

use Symfony\Component\Uid\Uuid;

final readonly class DiscoverableTournamentItem
{
    public function __construct(
        public Uuid $tournamentId,
        public string $name,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
        public int $groupCount,
        public int $memberCount,
    ) {
    }
}
