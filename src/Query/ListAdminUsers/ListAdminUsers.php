<?php

declare(strict_types=1);

namespace App\Query\ListAdminUsers;

use App\Query\QueryMessage;

/**
 * @implements QueryMessage<list<AdminUserItem>>
 */
final readonly class ListAdminUsers implements QueryMessage
{
    public function __construct(
        public ?string $search = null,
        public ?bool $verified = null,
        public ?bool $active = null,
    ) {
    }
}
