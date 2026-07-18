<?php

declare(strict_types=1);

namespace App\Command\RecalculateMatchSourcePoints;

use Symfony\Component\Uid\Uuid;

final readonly class RecalculateMatchSourcePointsCommand
{
    public function __construct(
        public Uuid $matchSourceId,
    ) {
    }
}
