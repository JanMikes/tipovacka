<?php

declare(strict_types=1);

namespace App\Query\ListGroupsForTournament;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<GroupTournamentListItem>>
 */
final readonly class ListGroupsForTournament implements QueryMessage
{
    public function __construct(
        public Uuid $tournamentId,
    ) {
    }
}
