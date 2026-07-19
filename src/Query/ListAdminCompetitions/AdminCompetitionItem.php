<?php

declare(strict_types=1);

namespace App\Query\ListAdminCompetitions;

use Symfony\Component\Uid\Uuid;

final readonly class AdminCompetitionItem
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public string $ownerNickname,
        public int $memberCount,
        public bool $isDeleted,
        public bool $isGlobal,
        public int $entryFeeCredits,
    ) {
    }
}
