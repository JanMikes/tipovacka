<?php

declare(strict_types=1);

namespace App\Command\CreatePublicMatchSource;

use Symfony\Component\Uid\Uuid;

final readonly class CreatePublicMatchSourceCommand
{
    public function __construct(
        public Uuid $adminId,
        public string $name,
        public ?string $description,
        public ?\DateTimeImmutable $startAt,
        public ?\DateTimeImmutable $endAt,
    ) {
    }
}
