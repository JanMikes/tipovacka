<?php

declare(strict_types=1);

namespace App\Query\ListMyOpenJoinRequests;

use App\Entity\CompetitionJoinRequest;
use App\Repository\CompetitionJoinRequestRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyOpenJoinRequestsQuery
{
    public function __construct(
        private CompetitionJoinRequestRepository $joinRequestRepository,
    ) {
    }

    /**
     * @return list<MyJoinRequestItem>
     */
    public function __invoke(ListMyOpenJoinRequests $query): array
    {
        $requests = $this->joinRequestRepository->findPendingByUser($query->userId);

        return array_map(
            static fn (CompetitionJoinRequest $r): MyJoinRequestItem => new MyJoinRequestItem(
                requestId: $r->id,
                competitionId: $r->competition->id,
                competitionName: $r->competition->name,
                matchSourceName: $r->competition->matchSource->name,
                requestedAt: $r->requestedAt,
            ),
            $requests,
        );
    }
}
