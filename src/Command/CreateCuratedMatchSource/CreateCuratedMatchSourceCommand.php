<?php

declare(strict_types=1);

namespace App\Command\CreateCuratedMatchSource;

use Symfony\Component\Uid\Uuid;

final readonly class CreateCuratedMatchSourceCommand
{
    public function __construct(
        public Uuid $adminId,
        public Uuid $sportId,
        public string $name,
        public ?string $description,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
    ) {
    }
}
