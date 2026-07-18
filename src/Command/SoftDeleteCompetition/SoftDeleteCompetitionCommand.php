<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteCompetition;

use Symfony\Component\Uid\Uuid;

final readonly class SoftDeleteCompetitionCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
    ) {
    }
}
