<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteSportMatch;

use Symfony\Component\Uid\Uuid;

final readonly class SoftDeleteSportMatchCommand
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $editorId,
    ) {
    }
}
