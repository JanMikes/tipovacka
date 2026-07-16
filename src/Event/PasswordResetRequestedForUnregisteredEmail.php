<?php

declare(strict_types=1);

namespace App\Event;

final readonly class PasswordResetRequestedForUnregisteredEmail
{
    public function __construct(
        public string $email,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
