<?php

declare(strict_types=1);

namespace App\Command\UpdateTeam;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateTeamCommand
{
    public function __construct(
        public Uuid $teamId,
        public string $name,
        public ?string $shortName,
        public ?string $country,
        public ?string $brandColor,
    ) {
    }
}
