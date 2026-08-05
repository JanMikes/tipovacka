<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetitionScope;

use App\Value\CompetitionSourceSpec;
use Symfony\Component\Uid\Uuid;

/**
 * „Zápasy soutěže", after the soutěž exists: the WHOLE basket of zdroje as the
 * organizer now wants it. The counterpart of the create wizard's step 1 — same
 * {@see CompetitionSourceSpec} layers, same editor, only now they are reconciled
 * against rows that already exist instead of being created from nothing.
 *
 * The list is authoritative: a zdroj missing from it leaves the soutěž, a new one
 * joins it, and a layer whose mode changed is rewritten. A `null` `matchSourceId`
 * means „vlastní zápasy" — the soutěž's own private zdroj, created on demand.
 *
 * Private competitions only. A global competition's scope is an admin decision
 * ({@see \App\Command\UpdateGlobalCompetition\UpdateGlobalCompetitionCommand}).
 */
final readonly class UpdateCompetitionScopeCommand
{
    /**
     * @param list<CompetitionSourceSpec> $layers the desired scope, in display order; must not be empty
     */
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
        public array $layers,
    ) {
    }
}
