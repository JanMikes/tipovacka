<?php

declare(strict_types=1);

namespace App\Query\ListGroupsForTournament;

use Symfony\Component\Uid\Uuid;

final readonly class GroupTournamentListItem
{
    public function __construct(
        public Uuid $groupId,
        public string $groupName,
        public Uuid $ownerId,
        public string $ownerNickname,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
