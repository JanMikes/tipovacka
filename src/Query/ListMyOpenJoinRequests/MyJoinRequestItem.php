<?php

declare(strict_types=1);

namespace App\Query\ListMyOpenJoinRequests;

use Symfony\Component\Uid\Uuid;

final readonly class MyJoinRequestItem
{
    public function __construct(
        public Uuid $requestId,
        public Uuid $competitionId,
        public string $competitionName,
        public string $matchSourceName,
        public \DateTimeImmutable $requestedAt,
    ) {
    }
}
