<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Query\GetMemberLeaderboardBreakdown\GetMemberLeaderboardBreakdown;
use App\Query\QueryBus;
use App\Repository\GroupRepository;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{groupId}/zebricek/clen/{userId}',
    name: 'portal_group_leaderboard_member',
    requirements: ['groupId' => Requirement::UUID, 'userId' => Requirement::UUID],
)]
final class MemberBreakdownController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $groupId, string $userId): Response
    {
        $group = $this->groupRepository->get(Uuid::fromString($groupId));
        $this->denyAccessUnlessGranted(LeaderboardVoter::VIEW, $group);

        $breakdown = $this->queryBus->handle(new GetMemberLeaderboardBreakdown(
            groupId: $group->id,
            userId: Uuid::fromString($userId),
        ));

        return $this->render('portal/leaderboard/member.html.twig', [
            'group' => $group,
            'breakdown' => $breakdown,
        ]);
    }
}
