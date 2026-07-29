<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionCurrentRound;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * „Which round (kolo/fáze) is this competition in right now" — feeds the
 * Žebříček hero stat („KOLO · Osmifinále") and lets a page decide whether a
 * round-scoped surface is meaningful at all.
 *
 * @implements QueryMessage<CompetitionCurrentRoundResult>
 */
final readonly class GetCompetitionCurrentRound implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
    ) {
    }
}
