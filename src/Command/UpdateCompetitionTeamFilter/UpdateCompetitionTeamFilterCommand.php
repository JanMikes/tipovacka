<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionTeamFilter;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateCompetitionTeamFilterCommand
{
    /**
     * Full replace: teams missing from $teamIds are dropped from the filter.
     * Guesses for now-excluded matches are kept — they simply stop counting
     * (CompetitionMatchProvider excludes them everywhere).
     *
     * @param list<Uuid> $teamIds
     */
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
        public array $teamIds,
    ) {
    }
}
