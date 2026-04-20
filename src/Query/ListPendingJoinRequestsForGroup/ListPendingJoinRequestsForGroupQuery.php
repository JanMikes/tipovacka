<?php

declare(strict_types=1);

namespace App\Query\ListPendingJoinRequestsForGroup;

use App\Entity\GroupJoinRequest;
use App\Repository\GroupJoinRequestRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListPendingJoinRequestsForGroupQuery
{
    public function __construct(
        private GroupJoinRequestRepository $joinRequestRepository,
    ) {
    }

    /**
     * @return list<JoinRequestListItem>
     */
    public function __invoke(ListPendingJoinRequestsForGroup $query): array
    {
        $requests = $this->joinRequestRepository->findPendingByGroup($query->groupId);

        return array_map(
            static fn (GroupJoinRequest $r): JoinRequestListItem => new JoinRequestListItem(
                requestId: $r->id,
                userId: $r->user->id,
                nickname: $r->user->nickname,
                requestedAt: $r->requestedAt,
            ),
            $requests,
        );
    }
}
