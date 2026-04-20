<?php

declare(strict_types=1);

namespace App\Command\BlockUser;

use Symfony\Component\Uid\Uuid;

final readonly class BlockUserCommand
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
