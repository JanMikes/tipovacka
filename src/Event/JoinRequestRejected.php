<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class JoinRequestRejected
{
    public function __construct(
        public Uuid $requestId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
