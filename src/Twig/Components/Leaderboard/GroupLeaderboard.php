<?php

declare(strict_types=1);

namespace App\Twig\Components\Leaderboard;

use App\Entity\Group;
use App\Query\GetGroupLeaderboard\GetGroupLeaderboard;
use App\Query\GetGroupLeaderboard\GroupLeaderboardResult;
use App\Query\QueryBus;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'Leaderboard:GroupLeaderboard')]
final class GroupLeaderboard
{
    use DefaultActionTrait;

    #[LiveProp]
    public Group $group;

    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public GroupLeaderboardResult $leaderboard {
        get => $this->queryBus->handle(new GetGroupLeaderboard(groupId: $this->group->id));
    }
}
