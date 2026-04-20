<?php

declare(strict_types=1);

namespace App\Query\ListAdminTournaments;

use App\Enum\TournamentVisibility;
use Symfony\Component\Uid\Uuid;

final readonly class AdminTournamentItem
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public TournamentVisibility $visibility,
        public string $sportCode,
        public string $ownerNickname,
        public bool $isFinished,
        public bool $isDeleted,
        public int $groupCount,
    ) {
    }
}
