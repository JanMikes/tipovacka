<?php

declare(strict_types=1);

namespace App\Query\ListGroupsForTournament;

use App\Entity\Group;
use App\Repository\GroupRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListGroupsForTournamentQuery
{
    public function __construct(
        private GroupRepository $groupRepository,
    ) {
    }

    /**
     * @return list<GroupTournamentListItem>
     */
    public function __invoke(ListGroupsForTournament $query): array
    {
        $groups = $this->groupRepository->findByTournament($query->tournamentId);

        return array_values(array_map(
            static fn (Group $g): GroupTournamentListItem => new GroupTournamentListItem(
                groupId: $g->id,
                groupName: $g->name,
                ownerId: $g->owner->id,
                ownerNickname: $g->owner->displayName,
                createdAt: $g->createdAt,
            ),
            $groups,
        ));
    }
}
