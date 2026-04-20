<?php

declare(strict_types=1);

namespace App\Command\ResetUserPassword;

use Symfony\Component\Uid\Uuid;

final readonly class ResetUserPasswordCommand
{
    public function __construct(
        public Uuid $userId,
        public string $plainPassword,
    ) {
    }
}
