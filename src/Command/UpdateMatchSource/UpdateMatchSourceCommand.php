<?php

declare(strict_types=1);

namespace App\Command\UpdateMatchSource;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateMatchSourceCommand
{
    public function __construct(
        public Uuid $matchSourceId,
        public string $name,
        public ?string $description,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
    ) {
    }
}
