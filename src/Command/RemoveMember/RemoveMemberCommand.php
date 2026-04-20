<?php

declare(strict_types=1);

namespace App\Command\RemoveMember;

use Symfony\Component\Uid\Uuid;

final readonly class RemoveMemberCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $groupId,
        public Uuid $targetUserId,
    ) {
    }
}
