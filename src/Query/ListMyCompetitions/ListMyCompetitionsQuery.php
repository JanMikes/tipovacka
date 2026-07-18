<?php

declare(strict_types=1);

namespace App\Query\ListMyCompetitions;

use App\Entity\Membership;
use App\Repository\MembershipRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyCompetitionsQuery
{
    public function __construct(
        private MembershipRepository $membershipRepository,
    ) {
    }

    /**
     * @return list<CompetitionListItem>
     */
    public function __invoke(ListMyCompetitions $query): array
    {
        $memberships = $this->membershipRepository->findMyActive($query->userId);

        return array_map(
            static fn (Membership $m): CompetitionListItem => new CompetitionListItem(
                competitionId: $m->competition->id,
                competitionName: $m->competition->name,
                matchSourceId: $m->competition->matchSource->id,
                matchSourceName: $m->competition->matchSource->name,
                matchSourceIsCompleted: $m->competition->matchSource->isCompleted,
                ownerNickname: $m->competition->owner->displayName,
                isOwner: $m->user->id->equals($m->competition->owner->id),
                joinedAt: $m->joinedAt,
            ),
            $memberships,
        );
    }
}
