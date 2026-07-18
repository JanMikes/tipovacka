<?php

declare(strict_types=1);

namespace App\Query\ListMyCompetitions;

use Symfony\Component\Uid\Uuid;

final readonly class CompetitionListItem
{
    public function __construct(
        public Uuid $competitionId,
        public string $competitionName,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public bool $matchSourceIsFinished,
        public string $ownerNickname,
        public bool $isOwner,
        public \DateTimeImmutable $joinedAt,
    ) {
    }
}
