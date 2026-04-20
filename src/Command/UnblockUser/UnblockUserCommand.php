<?php

declare(strict_types=1);

namespace App\Command\UnblockUser;

use Symfony\Component\Uid\Uuid;

final readonly class UnblockUserCommand
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
