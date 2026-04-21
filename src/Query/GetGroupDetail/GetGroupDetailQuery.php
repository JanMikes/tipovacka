<?php

declare(strict_types=1);

namespace App\Query\GetGroupDetail;

use App\Entity\Membership;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetGroupDetailQuery
{
    public function __construct(
        private GroupRepository $groupRepository,
        private MembershipRepository $membershipRepository,
    ) {
    }

    public function __invoke(GetGroupDetail $query): GetGroupDetailResult
    {
        $group = $this->groupRepository->get($query->groupId);
        $memberships = $this->membershipRepository->findActiveByGroup($group->id);

        $canSeeSecrets = $query->viewerIsAdmin || $group->owner->id->equals($query->viewerId);

        $members = array_map(
            static fn (Membership $m): GroupMemberListItem => new GroupMemberListItem(
                userId: $m->user->id,
                displayName: $m->user->displayName,
                joinedAt: $m->joinedAt,
                isOwner: $m->user->id->equals($m->group->owner->id),
                isAnonymous: $m->user->isAnonymous,
            ),
            $memberships,
        );

        return new GetGroupDetailResult(
            id: $group->id,
            tournamentId: $group->tournament->id,
            tournamentName: $group->tournament->name,
            tournamentIsFinished: $group->tournament->isFinished,
            ownerId: $group->owner->id,
            ownerNickname: $group->owner->displayName,
            name: $group->name,
            description: $group->description,
            pin: $canSeeSecrets ? $group->pin : null,
            shareableLinkToken: $canSeeSecrets ? $group->shareableLinkToken : null,
            createdAt: $group->createdAt,
            updatedAt: $group->updatedAt,
            members: $members,
        );
    }
}
