<?php

declare(strict_types=1);

namespace App\Command\CreateGroup;

use Symfony\Component\Uid\Uuid;

final readonly class CreateGroupCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $tournamentId,
        public string $name,
        public ?string $description,
        public bool $withPin,
    ) {
    }
}
