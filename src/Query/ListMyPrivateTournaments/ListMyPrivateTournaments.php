<?php

declare(strict_types=1);

namespace App\Query\ListMyPrivateTournaments;

use App\Query\ListActivePublicTournaments\TournamentListItem;
use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<TournamentListItem>>
 */
final readonly class ListMyPrivateTournaments implements QueryMessage
{
    public function __construct(
        public Uuid $ownerId,
    ) {
    }
}
