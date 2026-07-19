<?php

declare(strict_types=1);

namespace App\Command\UnlockCompetitionTips;

use Symfony\Component\Uid\Uuid;

final readonly class UnlockCompetitionTipsCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
    ) {
    }
}
