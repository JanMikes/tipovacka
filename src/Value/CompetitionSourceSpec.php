<?php

declare(strict_types=1);

namespace App\Value;

use App\Enum\CompetitionMatchSelectionMode;
use Symfony\Component\Uid\Uuid;

/**
 * One requested scope layer, as the wizard and the manage surface describe it
 * before it becomes a {@see \App\Entity\CompetitionSource}: a zdroj plus the
 * rule for taking its matches.
 *
 * `$matchSourceId` is null for „Moje zápasy" — the competition's own private
 * zdroj, created on demand so the user never meets the concept.
 */
final readonly class CompetitionSourceSpec
{
    /**
     * @param list<Uuid> $selectedMatchIds only meaningful when $selectionMode is Subset
     * @param list<Uuid> $filterTeamIds    only meaningful when $selectionMode is Teams
     */
    public function __construct(
        public ?Uuid $matchSourceId,
        public CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All,
        public bool $includePlayoff = true,
        public array $selectedMatchIds = [],
        public array $filterTeamIds = [],
    ) {
    }
}
