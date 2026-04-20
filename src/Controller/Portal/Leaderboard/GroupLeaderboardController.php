<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

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
    ) {
    }

    public function __invoke(string $groupId): Response
    {
        $group = $this->groupRepository->get(Uuid::fromString($groupId));
        $this->denyAccessUnlessGranted(LeaderboardVoter::VIEW, $group);

        return $this->render('portal/leaderboard/index.html.twig', [
            'group' => $group,
        ]);
    }
}
