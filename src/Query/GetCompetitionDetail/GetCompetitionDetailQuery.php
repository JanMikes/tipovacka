<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionDetail;

use App\Entity\Membership;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionDetailQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
    ) {
    }

    public function __invoke(GetCompetitionDetail $query): GetCompetitionDetailResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);
        $memberships = $this->membershipRepository->findActiveByCompetition($competition->id);

        $canSeeSecrets = $query->viewerIsAdmin || $competition->owner->id->equals($query->viewerId);

        $members = array_map(
            static function (Membership $m): CompetitionMemberListItem {
                $user = $m->user;
                $hasNickname = null !== $user->nickname && '' !== $user->nickname;
                $hasFullName = '' !== $user->fullName;

                return new CompetitionMemberListItem(
                    userId: $user->id,
                    displayName: $user->displayName,
                    fullName: ($hasNickname && $hasFullName) ? $user->fullName : null,
                    joinedAt: $m->joinedAt,
                    isOwner: $user->id->equals($m->competition->owner->id),
                    isAnonymous: $user->isAnonymous,
                );
            },
            $memberships,
        );

        return new GetCompetitionDetailResult(
            id: $competition->id,
            matchSourceId: $competition->matchSource->id,
            matchSourceName: $competition->matchSource->name,
            matchSourceIsFinished: $competition->matchSource->isFinished,
            ownerId: $competition->owner->id,
            ownerNickname: $competition->owner->displayName,
            name: $competition->name,
            description: $competition->description,
            pin: $canSeeSecrets ? $competition->pin : null,
            shareableLinkToken: $canSeeSecrets ? $competition->shareableLinkToken : null,
            createdAt: $competition->createdAt,
            updatedAt: $competition->updatedAt,
            members: $members,
        );
    }
}
