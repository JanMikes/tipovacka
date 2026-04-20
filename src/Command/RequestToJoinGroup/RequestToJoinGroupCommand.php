<?php

declare(strict_types=1);

namespace App\Command\RequestToJoinGroup;

use Symfony\Component\Uid\Uuid;

final readonly class RequestToJoinGroupCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $groupId,
    ) {
    }
}
