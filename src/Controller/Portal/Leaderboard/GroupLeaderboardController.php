<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Entity\User;
use App\Query\GetGroupLeaderboard\GetGroupLeaderboard;
use App\Query\ListMyGroups\ListMyGroups;
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

        /** @var User $user */
        $user = $this->getUser();
        $myGroups = $this->queryBus->handle(new ListMyGroups(userId: $user->id));

        $leaderboard = $this->queryBus->handle(new GetGroupLeaderboard(groupId: $group->id));

        $winner = null;
        if ($group->tournament->isFinished) {
            foreach ($leaderboard->rows as $row) {
                if (1 === $row->rank) {
                    $winner = $row;

                    break;
                }
            }
        }

        // Top-3 podium — only meaningful once there are ≥3 players and someone has scored.
        $podiumRows = [];
        if (count($leaderboard->rows) >= 3 && $leaderboard->rows[0]->totalPoints > 0) {
            $podiumRows = array_slice($leaderboard->rows, 0, 3);
        }

        return $this->render('portal/leaderboard/index.html.twig', [
            'group' => $group,
            'winner' => $winner,
            'my_groups' => $myGroups,
            'podium_rows' => $podiumRows,
        ]);
    }
}
