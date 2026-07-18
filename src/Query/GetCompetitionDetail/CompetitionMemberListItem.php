<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionDetail;

use Symfony\Component\Uid\Uuid;

final readonly class CompetitionMemberListItem
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
