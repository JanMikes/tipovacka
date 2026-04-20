<?php

declare(strict_types=1);

namespace App\Command\MarkSportMatchLive;

use Symfony\Component\Uid\Uuid;

final readonly class MarkSportMatchLiveCommand
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $editorId,
    ) {
    }
}
