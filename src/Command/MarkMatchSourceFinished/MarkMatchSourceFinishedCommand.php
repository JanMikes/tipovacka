<?php

declare(strict_types=1);

namespace App\Command\MarkMatchSourceFinished;

use Symfony\Component\Uid\Uuid;

final readonly class MarkMatchSourceFinishedCommand
{
    public function __construct(
        public Uuid $matchSourceId,
    ) {
    }
}
