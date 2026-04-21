<?php

declare(strict_types=1);

namespace App\Query\ListMyGroups;

use App\Entity\Membership;
use App\Repository\MembershipRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyGroupsQuery
{
    public function __construct(
        private MembershipRepository $membershipRepository,
    ) {
    }

    /**
     * @return list<GroupListItem>
     */
    public function __invoke(ListMyGroups $query): array
    {
        $memberships = $this->membershipRepository->findMyActive($query->userId);

        return array_map(
            static fn (Membership $m): GroupListItem => new GroupListItem(
                groupId: $m->group->id,
                groupName: $m->group->name,
                tournamentId: $m->group->tournament->id,
                tournamentName: $m->group->tournament->name,
                tournamentIsFinished: $m->group->tournament->isFinished,
                ownerNickname: $m->group->owner->displayName,
                isOwner: $m->user->id->equals($m->group->owner->id),
                joinedAt: $m->joinedAt,
            ),
            $memberships,
        );
    }
}
