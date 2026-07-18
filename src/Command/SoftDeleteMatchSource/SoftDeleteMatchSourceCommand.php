<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteMatchSource;

use Symfony\Component\Uid\Uuid;

final readonly class SoftDeleteMatchSourceCommand
{
    public function __construct(
        public Uuid $matchSourceId,
    ) {
    }
}
