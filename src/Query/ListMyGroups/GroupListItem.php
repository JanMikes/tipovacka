<?php

declare(strict_types=1);

namespace App\Query\ListMyGroups;

use Symfony\Component\Uid\Uuid;

final readonly class GroupListItem
{
    public function __construct(
        public Uuid $groupId,
        public string $groupName,
        public Uuid $tournamentId,
        public string $tournamentName,
        public bool $tournamentIsFinished,
        public string $ownerNickname,
        public bool $isOwner,
        public \DateTimeImmutable $joinedAt,
    ) {
    }
}
