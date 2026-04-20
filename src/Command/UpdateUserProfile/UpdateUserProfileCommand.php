<?php

declare(strict_types=1);

namespace App\Command\UpdateUserProfile;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateUserProfileCommand
{
    public function __construct(
        public Uuid $userId,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $phone,
    ) {
    }
}
