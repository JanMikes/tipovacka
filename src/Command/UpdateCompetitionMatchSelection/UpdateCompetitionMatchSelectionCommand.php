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
     * Scoped to ONE scope layer: a soutěž may hand-pick from several zdroje,
     * and each layer's selection is edited on its own. Null means „the first
     * one", which is every single-zdroj competition.
     *
     * @param list<Uuid> $selectedMatchIds
     */
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
        public array $selectedMatchIds,
        public ?Uuid $competitionSourceId = null,
    ) {
    }
}
