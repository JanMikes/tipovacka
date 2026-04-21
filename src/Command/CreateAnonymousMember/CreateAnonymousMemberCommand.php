<?php

declare(strict_types=1);

namespace App\Command\CreateAnonymousMember;

use Symfony\Component\Uid\Uuid;

final readonly class CreateAnonymousMemberCommand
{
    public function __construct(
        public Uuid $groupId,
        public Uuid $actorId,
        public string $firstName,
        public string $lastName,
        public ?string $nickname = null,
    ) {
    }
}
