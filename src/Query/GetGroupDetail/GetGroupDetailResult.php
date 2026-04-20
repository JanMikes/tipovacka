<?php

declare(strict_types=1);

namespace App\Query\GetGroupDetail;

use Symfony\Component\Uid\Uuid;

final readonly class GetGroupDetailResult
{
    /**
     * @param list<GroupMemberListItem> $members
     */
    public function __construct(
        public Uuid $id,
        public Uuid $tournamentId,
        public string $tournamentName,
        public bool $tournamentIsFinished,
        public Uuid $ownerId,
        public string $ownerNickname,
        public string $name,
        public ?string $description,
        public ?string $pin,
        public ?string $shareableLinkToken,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public array $members,
    ) {
    }
}
