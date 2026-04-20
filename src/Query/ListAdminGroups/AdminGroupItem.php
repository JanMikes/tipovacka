<?php

declare(strict_types=1);

namespace App\Query\ListAdminGroups;

use Symfony\Component\Uid\Uuid;

final readonly class AdminGroupItem
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public Uuid $tournamentId,
        public string $tournamentName,
        public string $ownerNickname,
        public int $memberCount,
        public bool $isDeleted,
    ) {
    }
}
