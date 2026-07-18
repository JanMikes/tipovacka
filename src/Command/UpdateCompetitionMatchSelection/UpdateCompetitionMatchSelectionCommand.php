<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionMatchSelection;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateCompetitionMatchSelectionCommand
{
    /**
     * Full replace: matches missing from $selectedMatchIds are unselected.
     * Guesses for now-excluded matches are kept — they simply stop counting
     * (CompetitionMatchProvider excludes them everywhere).
     *
     * @param list<Uuid> $selectedMatchIds
     */
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
        public array $selectedMatchIds,
    ) {
    }
}
