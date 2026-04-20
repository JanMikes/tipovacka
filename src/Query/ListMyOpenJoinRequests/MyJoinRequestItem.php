<?php

declare(strict_types=1);

namespace App\Query\ListMyOpenJoinRequests;

use Symfony\Component\Uid\Uuid;

final readonly class MyJoinRequestItem
{
    public function __construct(
        public Uuid $requestId,
        public Uuid $groupId,
        public string $groupName,
        public string $tournamentName,
        public \DateTimeImmutable $requestedAt,
    ) {
    }
}
