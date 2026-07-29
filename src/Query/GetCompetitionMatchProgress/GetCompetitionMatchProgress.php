<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionMatchProgress;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * „How far along is this soutěž?" — the counts behind the Žebříček hero stat
 * „ODEHRÁNO 38 / 64" and its live indicator.
 *
 * Scope is the competition's OWN matches (mode `all` / `subset` / `teams` +
 * playoff), resolved through {@see \App\Service\Competition\CompetitionMatchProvider},
 * so the numbers match what the board actually scores. Cancelled matches never
 * count — they will never be played.
 *
 * @implements QueryMessage<CompetitionMatchProgressResult>
 */
final readonly class GetCompetitionMatchProgress implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
    ) {
    }
}
