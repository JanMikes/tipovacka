<?php

declare(strict_types=1);

namespace App\Query\ListActivePublicTournaments;

use App\Query\QueryMessage;

/**
 * @implements QueryMessage<list<TournamentListItem>>
 */
final readonly class ListActivePublicTournaments implements QueryMessage
{
}
