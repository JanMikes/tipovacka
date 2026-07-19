<?php

declare(strict_types=1);

namespace App\Command\LockCompetitionTips;

use Symfony\Component\Uid\Uuid;

final readonly class LockCompetitionTipsCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
    ) {
    }
}
