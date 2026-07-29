<?php

declare(strict_types=1);

namespace App\Query\GetTeamForm;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Win / draw / loss record of one or more teams, counted over the FINISHED
 * matches a soutěž (Competition) includes — the „ARG · V2 R0 P0" sub-label under
 * a team name on the match detail page.
 *
 * Scope always comes from {@see \App\Service\Competition\CompetitionMatchProvider},
 * so the form agrees with every other number on the page (a Teams-mode or Subset
 * competition counts only what it actually includes). Both teams of a match are
 * asked for in ONE call — never one query per team, never one per row.
 *
 * @implements QueryMessage<TeamFormResult>
 */
final readonly class GetTeamForm implements QueryMessage
{
    /**
     * @param list<Uuid> $teamIds
     */
    public function __construct(
        public Uuid $competitionId,
        public array $teamIds,
    ) {
    }
}
