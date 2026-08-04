<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\MatchSource;
use App\Entity\Team;
use App\Repository\TeamAliasRepository;
use App\Repository\TeamRepository;
use App\Service\Identity\ProvideIdentity;

/**
 * The single home for the hybrid team-scope rule.
 *
 * A team NAME (typed in the match form, picked from the autocomplete, or read
 * from a CSV cell) resolves to a Team entity: on a curated source to a shared
 * GLOBAL directory team for that sport; on a private source to a LOCAL team of
 * that source. A name that misses is retried against TeamAlias rows in the same
 * scope (feed/import spellings map onto the one directory identity). Unknown
 * names are created (the directory / local pool grows) — exactly like Player
 * names flow through PlayerRepository::findOrCreate.
 */
final readonly class TeamResolver
{
    public function __construct(
        private TeamRepository $teams,
        private TeamAliasRepository $aliases,
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
     * Find-only lookup in the source's resolution scope — never creates. Tries
     * the team name first, then aliases in the same scope. Used by the reassign
     * guard, the import „nový tým" badge and the feed synchronizer's pending-team
     * gate, so a rejected/previewed edit never leaves a throwaway team behind.
     */
    public function findExisting(MatchSource $source, string $name): ?Team
    {
        $name = trim($name);

        if ($source->isCurated) {
            return $this->teams->findGlobalByName($source->sport->id, $name)
                ?? $this->aliases->findGlobalTeamByAlias($source->sport->id, $name);
        }

        return $this->teams->findLocalByName($source->id, $name)
            ?? $this->aliases->findLocalTeamByAlias($source->id, $name);
    }

    /**
     * Whether a team is in a source's resolution scope — same hybrid rule as
     * {@see resolve}: a curated source draws from the GLOBAL directory of its
     * sport, a private source only from its own LOCAL teams. The single guard for
     * the competition team filter (rejects a cross-source / cross-sport team id).
     */
    public function belongsToSourceScope(MatchSource $source, Team $team): bool
    {
        if (!$team->sport->id->equals($source->sport->id)) {
            return false;
        }

        return $source->isCurated
            ? null === $team->matchSource
            : null !== $team->matchSource && $team->matchSource->id->equals($source->id);
    }
}
