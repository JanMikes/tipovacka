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

        $existingTarget = $this->aliases->findGlobalTeamByAlias($command->sportId, $alias);
        if (null !== $existingTarget) {
            throw TeamAliasConflict::aliasAlreadyResolves($alias, $existingTarget->name);
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
