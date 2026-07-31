<?php

declare(strict_types=1);

namespace App\Command\CreateTeam;

use Symfony\Component\Uid\Uuid;

/**
 * Creates a GLOBAL directory team (match_source_id NULL) for a sport. Local teams
 * are never created here — they are born implicitly from a private source's matches.
 */
final readonly class CreateTeamCommand
{
    public function __construct(
        public Uuid $sportId,
        public string $name,
        public ?string $shortName,
        public ?string $country,
        public ?string $brandColor,
        /** Storage path of an already-stored logo (TeamLogoStorage), or null. */
        public ?string $logo = null,
    ) {
    }
}
