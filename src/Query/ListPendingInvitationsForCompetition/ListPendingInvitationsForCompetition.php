<?php

declare(strict_types=1);

namespace App\Query\ListPendingInvitationsForCompetition;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<PendingInvitationListItem>>
 */
final readonly class ListPendingInvitationsForCompetition implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
        public \DateTimeImmutable $now,
    ) {
    }
}
