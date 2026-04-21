<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Query\GetGroupGuessMatrix\GetGroupGuessMatrix;
use App\Query\QueryBus;
use App\Repository\GroupRepository;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{groupId}/zebricek/matice',
    name: 'portal_group_leaderboard_matrix',
    requirements: ['groupId' => Requirement::UUID],
)]
final class GuessMatrixController extends AbstractController
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

        $matrix = $this->queryBus->handle(new GetGroupGuessMatrix(groupId: $group->id));

        return $this->render('portal/leaderboard/matrix.html.twig', [
            'group' => $group,
            'matrix' => $matrix,
        ]);
    }
}
