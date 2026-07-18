<?php

declare(strict_types=1);

namespace App\Twig\Components\Leaderboard;

use App\Entity\Competition;
use App\Query\GetCompetitionLeaderboard\CompetitionLeaderboardResult;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\QueryBus;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'Leaderboard:CompetitionLeaderboard')]
final class CompetitionLeaderboard
{
    use DefaultActionTrait;

    #[LiveProp]
    public Competition $competition;

    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public CompetitionLeaderboardResult $leaderboard {
        get => $this->queryBus->handle(new GetCompetitionLeaderboard(competitionId: $this->competition->id));
    }
}
