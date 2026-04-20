<?php

declare(strict_types=1);

namespace App\Command\CancelSportMatch;

use Symfony\Component\Uid\Uuid;

final readonly class CancelSportMatchCommand
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $editorId,
    ) {
    }
}
