<?php

declare(strict_types=1);

namespace App\Command\CreateGlobalCompetition;

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
     * @param array<string, array{enabled: bool, points: int}> $ruleChanges rule identifier → desired state (over the defaults)
     */
    public function __construct(
        public Uuid $adminId,
        public Uuid $matchSourceId,
        public string $name,
        public int $entryFeeCredits,
        public CompetitionMonetization $monetization = CompetitionMonetization::None,
        public array $ruleChanges = [],
    ) {
    }
}
