<?php

declare(strict_types=1);

namespace App\Query\GetGroupDetail;

use Symfony\Component\Uid\Uuid;

final readonly class GroupMemberListItem
{
    public function __construct(
        public Uuid $userId,
        public string $displayName,
        public ?string $fullName,
        public \DateTimeImmutable $joinedAt,
        public bool $isOwner,
        public bool $isAnonymous,
    ) {
    }
}
