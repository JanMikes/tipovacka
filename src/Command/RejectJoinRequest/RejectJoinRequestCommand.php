<?php

declare(strict_types=1);

namespace App\Command\RejectJoinRequest;

use Symfony\Component\Uid\Uuid;

final readonly class RejectJoinRequestCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $requestId,
    ) {
    }
}
