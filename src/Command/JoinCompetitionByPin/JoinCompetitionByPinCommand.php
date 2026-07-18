<?php

declare(strict_types=1);

namespace App\Command\JoinCompetitionByPin;

use Symfony\Component\Uid\Uuid;

final readonly class JoinCompetitionByPinCommand
{
    public function __construct(
        public Uuid $userId,
        public string $pin,
    ) {
    }
}
