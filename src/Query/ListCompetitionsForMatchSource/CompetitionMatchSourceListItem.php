<?php

declare(strict_types=1);

namespace App\Query\ListCompetitionsForMatchSource;

use Symfony\Component\Uid\Uuid;

final readonly class CompetitionMatchSourceListItem
{
    public function __construct(
        public Uuid $competitionId,
        public string $competitionName,
        public Uuid $ownerId,
        public string $ownerNickname,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
