<?php

declare(strict_types=1);

namespace App\Query\GetTeamForm;

use Symfony\Component\Uid\Uuid;

final readonly class TeamFormResult
{
    /**
     * @param array<string, TeamForm> $byTeam keyed by team id RFC4122; a team with no
     *                                        finished match is absent, never zeroed
     */
    public function __construct(
        private array $byTeam,
    ) {
    }

    public function for(Uuid $teamId): ?TeamForm
    {
        return $this->byTeam[$teamId->toRfc4122()] ?? null;
    }
}
