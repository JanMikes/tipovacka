<?php

declare(strict_types=1);

namespace App\Query\ListDiscoverableGlobalCompetitions;

use Symfony\Component\Uid\Uuid;

final readonly class DiscoverableGlobalCompetitionItem
{
    public function __construct(
        public Uuid $competitionId,
        public string $name,
        public string $sportName,
        public ?\DateTimeImmutable $sourceStartAt,
        public ?\DateTimeImmutable $sourceEndAt,
        public int $entryFeeCredits,
        public int $playerCount,
        public bool $viewerIsMember,
    ) {
    }
}
