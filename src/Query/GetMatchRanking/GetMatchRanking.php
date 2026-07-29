<?php

declare(strict_types=1);

namespace App\Query\GetMatchRanking;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Per-match tip board ("Pořadí za zápas") — every active guess on one match
 * within one soutěž (Competition), ordered by the points it scored there.
 *
 * A match that is not finished yet has no evaluations (they are written when the
 * final score lands), so its rows come back unranked and point-less — a plain
 * alphabetical tip board. See {@see MatchRankingResult::$isScored}. Revealing
 * those tips is a VISIBILITY decision the caller owes
 * ({@see \App\Service\Competition\TipVisibilityGate}); this query does not gate.
 *
 * @implements QueryMessage<MatchRankingResult>
 */
final readonly class GetMatchRanking implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $sportMatchId,
    ) {
    }
}
