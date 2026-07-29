<?php

declare(strict_types=1);

namespace App\Query\ListUserMatches;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * All matches across every soutěž (Competition) the user belongs to, in any state.
 * Powers the cross-soutěž „Zápasy" page and — scoped with `$competitionId` — the
 * „Následující / Odehrané zápasy" sections of the Nástěnka, whose switcher picks
 * one soutěž at a time.
 *
 * @implements QueryMessage<list<UserMatchItem>>
 */
final readonly class ListUserMatches implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
        /**
         * Narrow the feed to ONE of the user's soutěže — matches it does not include
         * drop out, and every row carries only that soutěž's „Rozložení tipů".
         * Null = every soutěž the user is in (the /zapasy feed).
         */
        public ?Uuid $competitionId = null,
    ) {
    }
}
