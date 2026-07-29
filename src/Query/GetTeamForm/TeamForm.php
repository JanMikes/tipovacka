<?php

declare(strict_types=1);

namespace App\Query\GetTeamForm;

/**
 * One team's record over the finished matches of a soutěž. Only ever built for a
 * team that has actually played — a team with nothing behind it is absent from
 * {@see TeamFormResult}, so the UI renders nothing instead of „V0 R0 P0".
 */
final readonly class TeamForm
{
    public function __construct(
        public int $wins,
        public int $draws,
        public int $losses,
        public int $played,
    ) {
    }
}
