<?php

declare(strict_types=1);

namespace App\Command\CreateGlobalCompetition;

use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use Symfony\Component\Uid\Uuid;

/**
 * Admin-area command that stands up a global (publicly discoverable) competition
 * over an existing curated source. Composes the competition (isGlobal, selection
 * mode All, owner = the creating admin), the admin's owner membership and the
 * per-rule configuration in ONE transaction. See .docs/DOMAIN.md §Global competitions.
 */
final readonly class CreateGlobalCompetitionCommand
{
    /**
     * @param array<string, array{enabled: bool, points: int}> $ruleChanges   rule identifier → desired state (over the defaults)
     * @param list<Uuid>                                       $filterTeamIds only used when $selectionMode is Teams
     */
    public function __construct(
        public Uuid $adminId,
        public Uuid $matchSourceId,
        public string $name,
        public int $entryFeeCredits,
        public ?string $description = null,
        public CompetitionMonetization $monetization = CompetitionMonetization::None,
        public array $ruleChanges = [],
        public CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All,
        public array $filterTeamIds = [],
    ) {
    }
}
