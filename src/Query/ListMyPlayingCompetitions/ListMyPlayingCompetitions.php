<?php

declare(strict_types=1);

namespace App\Query\ListMyPlayingCompetitions;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * „Soutěže, kde tipuješ" (item 07): every competition the viewer is an active
 * member of, each with the standing and the one next action that belongs to it —
 * „Tipuj N" while something is still open, „Otevřít" otherwise.
 *
 * Deliberately NOT the full leaderboard per competition: rank, points and the
 * round gain are batch aggregates over all of the viewer's competitions at once.
 *
 * @implements QueryMessage<list<PlayingCompetitionItem>>
 */
final readonly class ListMyPlayingCompetitions implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
