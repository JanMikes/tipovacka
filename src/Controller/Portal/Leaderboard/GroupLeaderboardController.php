<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Query\GetGroupLeaderboard\GetGroupLeaderboard;
use App\Query\QueryBus;
use App\Repository\GroupRepository;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{groupId}/zebricek',
    name: 'portal_group_leaderboard',
    requirements: ['groupId' => Requirement::UUID],
)]
final class GroupLeaderboardController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $groupId): Response
    {
        $group = $this->groupRepository->get(Uuid::fromString($groupId));
        $this->denyAccessUnlessGranted(LeaderboardVoter::VIEW, $group);

        $winner = null;
        if ($group->tournament->isFinished) {
            $leaderboard = $this->queryBus->handle(new GetGroupLeaderboard(groupId: $group->id));
            foreach ($leaderboard->rows as $row) {
                if (1 === $row->rank) {
                    $winner = $row;

                    break;
                }
            }
        }

        return $this->render('portal/leaderboard/index.html.twig', [
            'group' => $group,
            'winner' => $winner,
        ]);
    }
}
