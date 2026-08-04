<?php

declare(strict_types=1);

namespace App\Command\AddTeamAlias;

use Symfony\Component\Uid\Uuid;

/**
 * Register an alternative spelling for a GLOBAL directory team, so feeds and
 * imports using that spelling resolve to the existing team instead of creating
 * a duplicate. Local (private-source) teams are out of scope — feeds only ever
 * write to curated sources.
 */
final readonly class AddTeamAliasCommand
{
    public function __construct(
        public Uuid $sportId,
        /** Existing directory team, matched by its current name (case-insensitive). */
        public string $teamName,
        public string $alias,
    ) {
    }
}
