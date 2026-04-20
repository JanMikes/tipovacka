<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class UserDeleted
{
    public function __construct(
        public Uuid $userId,
        public string $email,
        public string $nickname,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
