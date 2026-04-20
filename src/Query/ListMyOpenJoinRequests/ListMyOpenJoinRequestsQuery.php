<?php

declare(strict_types=1);

namespace App\Query\ListMyOpenJoinRequests;

use App\Entity\GroupJoinRequest;
use App\Repository\GroupJoinRequestRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyOpenJoinRequestsQuery
{
    public function __construct(
        private GroupJoinRequestRepository $joinRequestRepository,
    ) {
    }

    /**
     * @return list<MyJoinRequestItem>
     */
    public function __invoke(ListMyOpenJoinRequests $query): array
    {
        $requests = $this->joinRequestRepository->findPendingByUser($query->userId);

        return array_map(
            static fn (GroupJoinRequest $r): MyJoinRequestItem => new MyJoinRequestItem(
                requestId: $r->id,
                groupId: $r->group->id,
                groupName: $r->group->name,
                tournamentName: $r->group->tournament->name,
                requestedAt: $r->requestedAt,
            ),
            $requests,
        );
    }
}
