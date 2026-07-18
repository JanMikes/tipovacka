<?php

declare(strict_types=1);

namespace App\Command\MarkMatchSourceCompleted;

use Symfony\Component\Uid\Uuid;

final readonly class MarkMatchSourceCompletedCommand
{
    public function __construct(
        public Uuid $matchSourceId,
    ) {
    }
}
