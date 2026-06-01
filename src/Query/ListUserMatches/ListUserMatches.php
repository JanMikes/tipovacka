<?php

declare(strict_types=1);

namespace App\Query\ListUserMatches;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * All matches across every soutěž (Group) the user belongs to, in any state.
 * Powers the cross-soutěž "Zápasy" page (filter chips Vše / Dnes / Tipovatelné /
 * Ukončené — no Live). A broader sibling of {@see \App\Query\ListUpcomingMatchesForUser}.
 *
 * @implements QueryMessage<list<UserMatchItem>>
 */
final readonly class ListUserMatches implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
