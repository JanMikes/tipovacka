<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteUser;

use Symfony\Component\Uid\Uuid;

final readonly class SoftDeleteUserCommand
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
