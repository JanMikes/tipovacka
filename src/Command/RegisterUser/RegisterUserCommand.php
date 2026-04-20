<?php

declare(strict_types=1);

namespace App\Command\RegisterUser;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $nickname,
        public string $plainPassword,
        public bool $autoVerify = false,
    ) {
    }
}
