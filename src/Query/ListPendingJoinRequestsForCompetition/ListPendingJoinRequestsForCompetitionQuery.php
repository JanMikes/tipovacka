<?php

declare(strict_types=1);

namespace App\Query\ListPendingJoinRequestsForCompetition;

use App\Entity\CompetitionJoinRequest;
use App\Repository\CompetitionJoinRequestRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListPendingJoinRequestsForCompetitionQuery
{
    public function __construct(
        private CompetitionJoinRequestRepository $joinRequestRepository,
    ) {
    }

    /**
     * @return list<JoinRequestListItem>
     */
    public function __invoke(ListPendingJoinRequestsForCompetition $query): array
    {
        $requests = $this->joinRequestRepository->findPendingByCompetition($query->competitionId);

        return array_map(
            static fn (CompetitionJoinRequest $r): JoinRequestListItem => new JoinRequestListItem(
                requestId: $r->id,
                userId: $r->user->id,
                nickname: $r->user->displayName,
                requestedAt: $r->requestedAt,
            ),
            $requests,
        );
    }
}
