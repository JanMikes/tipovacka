<?php

declare(strict_types=1);

namespace App\Query\ListAdminUsers;

use Symfony\Component\Uid\Uuid;

final readonly class AdminUserItem
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public Uuid $id,
        public ?string $email,
        public ?string $nickname,
        public array $roles,
        public bool $isVerified,
        public bool $isActive,
        public bool $isDeleted,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
