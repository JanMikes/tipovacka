<?php

declare(strict_types=1);

namespace App\Twig\Components\Leaderboard;

use App\Entity\Competition;
use App\Enum\LeaderboardTimeFilter;
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

    /**
     * Time window (`celkem` / `7dni`); a string so it round-trips cleanly through
     * the component state and the `?obdobi` query param the tabs link to.
     */
    #[LiveProp]
    public string $filter = LeaderboardTimeFilter::AllTime->value;

    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public CompetitionLeaderboardResult $leaderboard {
        get => $this->queryBus->handle(new GetCompetitionLeaderboard(
            competitionId: $this->competition->id,
            filter: LeaderboardTimeFilter::fromRequest($this->filter),
        ));
    }
}
