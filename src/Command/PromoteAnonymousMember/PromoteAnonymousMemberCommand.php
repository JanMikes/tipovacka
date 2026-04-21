<?php

declare(strict_types=1);

namespace App\Command\PromoteAnonymousMember;

use Symfony\Component\Uid\Uuid;

final readonly class PromoteAnonymousMemberCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $groupId,
        public Uuid $actorId,
        public string $email,
    ) {
    }
}
