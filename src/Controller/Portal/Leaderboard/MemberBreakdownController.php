<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Query\GetMemberLeaderboardBreakdown\GetMemberLeaderboardBreakdown;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{competitionId}/zebricek/clen/{userId}',
    name: 'portal_competition_leaderboard_member',
    requirements: ['competitionId' => Requirement::UUID, 'userId' => Requirement::UUID],
)]
final class MemberBreakdownController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $competitionId, string $userId): Response
    {
        $competition = $this->competitionRepository->get(Uuid::fromString($competitionId));
        $this->denyAccessUnlessGranted(LeaderboardVoter::VIEW, $competition);

        $breakdown = $this->queryBus->handle(new GetMemberLeaderboardBreakdown(
            competitionId: $competition->id,
            userId: Uuid::fromString($userId),
        ));

        return $this->render('portal/leaderboard/member.html.twig', [
            'competition' => $competition,
            'breakdown' => $breakdown,
        ]);
    }
}
