<?php

declare(strict_types=1);

namespace App\Query\ListPendingInvitationsForGroup;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<PendingInvitationListItem>>
 */
final readonly class ListPendingInvitationsForGroup implements QueryMessage
{
    public function __construct(
        public Uuid $groupId,
        public \DateTimeImmutable $now,
    ) {
    }
}
