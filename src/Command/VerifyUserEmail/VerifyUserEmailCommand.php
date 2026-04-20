<?php

declare(strict_types=1);

namespace App\Command\VerifyUserEmail;

use Symfony\Component\Uid\Uuid;

final readonly class VerifyUserEmailCommand
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
