<?php

declare(strict_types=1);

namespace App\Query\ListAdminGroups;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<AdminGroupItem>>
 */
final readonly class ListAdminGroups implements QueryMessage
{
    public function __construct(
        public ?Uuid $tournamentId = null,
    ) {
    }
}
