<?php

declare(strict_types=1);

namespace App\Query\ListDiscoverablePublicTournaments;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<DiscoverableTournamentItem>>
 */
final readonly class ListDiscoverablePublicTournaments implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
