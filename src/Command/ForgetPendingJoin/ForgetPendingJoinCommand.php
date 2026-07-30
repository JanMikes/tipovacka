<?php

declare(strict_types=1);

namespace App\Command\ForgetPendingJoin;

use Symfony\Component\Uid\Uuid;

final readonly class ForgetPendingJoinCommand
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
