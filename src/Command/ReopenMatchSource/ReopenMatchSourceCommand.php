<?php

declare(strict_types=1);

namespace App\Command\ReopenMatchSource;

use Symfony\Component\Uid\Uuid;

final readonly class ReopenMatchSourceCommand
{
    public function __construct(
        public Uuid $matchSourceId,
    ) {
    }
}
