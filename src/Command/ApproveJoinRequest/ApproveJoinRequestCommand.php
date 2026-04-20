<?php

declare(strict_types=1);

namespace App\Command\ApproveJoinRequest;

use Symfony\Component\Uid\Uuid;

final readonly class ApproveJoinRequestCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $requestId,
    ) {
    }
}
