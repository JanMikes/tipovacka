<?php

declare(strict_types=1);

namespace App\Command\JoinGroupByPin;

use Symfony\Component\Uid\Uuid;

final readonly class JoinGroupByPinCommand
{
    public function __construct(
        public Uuid $userId,
        public string $pin,
    ) {
    }
}
