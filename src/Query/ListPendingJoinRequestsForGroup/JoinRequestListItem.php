<?php

declare(strict_types=1);

namespace App\Query\ListPendingJoinRequestsForGroup;

use Symfony\Component\Uid\Uuid;

final readonly class JoinRequestListItem
{
    public function __construct(
        public Uuid $requestId,
        public Uuid $userId,
        public string $nickname,
        public \DateTimeImmutable $requestedAt,
    ) {
    }
}
