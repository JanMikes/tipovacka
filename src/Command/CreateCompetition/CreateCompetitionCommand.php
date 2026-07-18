<?php

declare(strict_types=1);

namespace App\Command\CreateCompetition;

use Symfony\Component\Uid\Uuid;

final readonly class CreateCompetitionCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $matchSourceId,
        public string $name,
        public ?string $description,
        public bool $withPin,
    ) {
    }
}
