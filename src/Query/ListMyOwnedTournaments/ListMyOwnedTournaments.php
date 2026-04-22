<?php

declare(strict_types=1);

namespace App\Query\ListMyOwnedTournaments;

use App\Query\ListActivePublicTournaments\TournamentListItem;
use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<TournamentListItem>>
 */
final readonly class ListMyOwnedTournaments implements QueryMessage
{
    public function __construct(
        public Uuid $ownerId,
    ) {
    }
}
