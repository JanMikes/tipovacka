<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionLeaderboard;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * The competition's board — all-time, and only all-time. Item 15 retired the
 * period filter („Celkem / Poslední kolo / Týden / Měsíc"), so there is exactly
 * ONE board per competition and no caller can ask for a re-ranked window.
 *
 * @implements QueryMessage<CompetitionLeaderboardResult>
 */
final readonly class GetCompetitionLeaderboard implements QueryMessage
{
    public function __construct(
        public Uuid $competitionId,
    ) {
    }
}
