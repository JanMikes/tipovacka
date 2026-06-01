<?php

declare(strict_types=1);

namespace App\Query\GetMatchRanking;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Per-match ranking ("Pořadí za zápas") — the evaluated guesses for one match
 * within one soutěž (Group), ordered by points scored on that match.
 *
 * @implements QueryMessage<MatchRankingResult>
 */
final readonly class GetMatchRanking implements QueryMessage
{
    public function __construct(
        public Uuid $groupId,
        public Uuid $sportMatchId,
    ) {
    }
}
