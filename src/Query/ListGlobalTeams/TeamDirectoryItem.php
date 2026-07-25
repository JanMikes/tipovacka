<?php

declare(strict_types=1);

namespace App\Query\ListGlobalTeams;

use App\Value\TeamView;
use Symfony\Component\Uid\Uuid;

final readonly class TeamDirectoryItem
{
    public function __construct(
        public Uuid $sportId,
        public string $sportName,
        public TeamView $team,
        /** How many matches reference this team (home or away) — the „same team across matches" signal. */
        public int $matchCount,
    ) {
    }
}
