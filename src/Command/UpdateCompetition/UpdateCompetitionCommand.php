<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetition;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateCompetitionCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
        public string $name,
        public ?string $description,
        public bool $hideOthersTipsBeforeDeadline,
    ) {
    }
}
