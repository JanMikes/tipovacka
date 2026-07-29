<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Query\GetMemberLeaderboardBreakdown\GetMemberLeaderboardBreakdown;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Members only (`leaderboard_details`): a per-match breakdown of one member's
 * tips, which is more than the public board's points and ranks.
 */
#[Route(
    '/zebricek/clen/{userId}',
    name: 'leaderboard_member',
    requirements: ['userId' => Requirement::UUID],
    methods: ['GET'],
)]
#[IsGranted('ROLE_USER')]
final class MemberBreakdownController extends AbstractController
{
    use ResolvesLeaderboardCompetition;

    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request, string $userId): Response
    {
        $competition = $this->competitionRepository->get(self::competitionIdFromRequest($request));
        $this->denyAccessUnlessGranted(LeaderboardVoter::DETAILS, $competition);

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
