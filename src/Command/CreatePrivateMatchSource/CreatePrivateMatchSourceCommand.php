<?php

declare(strict_types=1);

namespace App\Command\CreatePrivateMatchSource;

use Symfony\Component\Uid\Uuid;

final readonly class CreatePrivateMatchSourceCommand
{
    public function __construct(
        public Uuid $ownerId,
        public Uuid $sportId,
        public string $name,
        public ?string $description,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
    ) {
    }
}
