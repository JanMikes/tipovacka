<?php

declare(strict_types=1);

namespace App\Command\UpdateTeam;

use Symfony\Component\Uid\Uuid;

/**
 * The logo is a three-state field: `$logo` set = point the team at that freshly
 * stored file, `$removeLogo` = drop the logo entirely, neither = leave it alone
 * (the common case — an admin editing the name must not lose the logo).
 */
final readonly class UpdateTeamCommand
{
    public function __construct(
        public Uuid $teamId,
        public string $name,
        public ?string $shortName,
        public ?string $country,
        public ?string $brandColor,
        /** Storage path of an already-stored logo (TeamLogoStorage), or null to keep the current one. */
        public ?string $logo = null,
        public bool $removeLogo = false,
    ) {
    }
}
