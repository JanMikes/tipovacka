<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\MatchSource;
use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Service\Identity\ProvideIdentity;

/**
 * The single home for the hybrid team-scope rule.
 *
 * A team NAME (typed in the match form, picked from the autocomplete, or read
 * from a CSV cell) resolves to a Team entity: on a curated source to a shared
 * GLOBAL directory team for that sport; on a private source to a LOCAL team of
 * that source. Unknown names are created (the directory / local pool grows) —
 * exactly like Player names flow through PlayerRepository::findOrCreate.
 */
final readonly class TeamResolver
{
    public function __construct(
        private TeamRepository $teams,
        private ProvideIdentity $identity,
    ) {
    }

    public function resolve(MatchSource $source, string $name, \DateTimeImmutable $now): Team
    {
        $name = trim($name);

        $existing = $this->findExisting($source, $name);
        if ($existing instanceof Team) {
            return $existing;
        }

        $team = new Team(
            id: $this->identity->next(),
            sport: $source->sport,
            matchSource: $source->isCurated ? null : $source,
            name: $name,
            createdAt: $now,
        );

        $this->teams->save($team);

        return $team;
    }

    /**
     * Find-only lookup in the source's resolution scope — never creates. Used by
     * the reassign guard and the import „nový tým" badge so a rejected/previewed
     * edit never leaves a throwaway team behind.
     */
    public function findExisting(MatchSource $source, string $name): ?Team
    {
        $name = trim($name);

        return $source->isCurated
            ? $this->teams->findGlobalByName($source->sport->id, $name)
            : $this->teams->findLocalByName($source->id, $name);
    }
}
