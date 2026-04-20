<?php

declare(strict_types=1);

namespace App\Command\LeaveGroup;

use Symfony\Component\Uid\Uuid;

final readonly class LeaveGroupCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $groupId,
    ) {
    }
}
