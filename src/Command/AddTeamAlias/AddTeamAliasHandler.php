<?php

declare(strict_types=1);

namespace App\Command\AddTeamAlias;

use App\Entity\TeamAlias;
use App\Exception\TeamAliasConflict;
use App\Exception\TeamNotFound;
use App\Repository\TeamAliasRepository;
use App\Repository\TeamRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddTeamAliasHandler
{
    public function __construct(
        private TeamRepository $teams,
        private TeamAliasRepository $aliases,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddTeamAliasCommand $command): TeamAlias
    {
        $alias = trim($command->alias);

        $team = $this->teams->findGlobalByName($command->sportId, trim($command->teamName))
            ?? throw TeamNotFound::withName(trim($command->teamName));

        // An alias that equals a directory team's NAME would shadow it — the name
        // lookup wins in TeamResolver, so such an alias could never take effect
        // (or worse: it targets a different team than the name resolves to).
        $shadowed = $this->teams->findGlobalByName($command->sportId, $alias);
        if (null !== $shadowed) {
            throw TeamAliasConflict::aliasShadowsTeamName($alias, $shadowed->name);
        }

        // Adding an alias that is already there, pointing where it is asked to
        // point, is a no-op and not an error. Aliases are added in batches from a
        // list an operator re-runs after fixing one line of it; failing the whole
        // run on the entries that already succeeded makes the list unrunnable
        // (it did, on 2026-08-08). A DIFFERENT team is still a real conflict.
        $existing = $this->aliases->findGlobalByAlias($command->sportId, $alias);
        if (null !== $existing) {
            if (!$existing->team->id->equals($team->id)) {
                throw TeamAliasConflict::aliasAlreadyResolves($alias, $existing->team->name);
            }

            return $existing;
        }

        $teamAlias = new TeamAlias(
            id: $this->identity->next(),
            team: $team,
            alias: $alias,
            createdAt: \DateTimeImmutable::createFromInterface($this->clock->now()),
        );

        $this->aliases->save($teamAlias);

        return $teamAlias;
    }
}
