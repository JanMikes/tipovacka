<?php

declare(strict_types=1);

namespace App\Command\CreateCompetition;

use App\Enum\CompetitionMatchSelectionMode;
use Symfony\Component\Uid\Uuid;

final readonly class CreateCompetitionCommand
{
    /**
     * @param list<Uuid> $selectedMatchIds only used when $selectionMode is Subset
     */
    public function __construct(
        public Uuid $ownerId,
        public Uuid $matchSourceId,
        public string $name,
        public ?string $description,
        public bool $withPin,
        public bool $hideOthersTipsBeforeDeadline = false,
        public CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All,
        public bool $includePlayoff = true,
        public array $selectedMatchIds = [],
    ) {
    }
}
