<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Entity\User;
use App\Query\GetCompetitionGuessMatrix\GetCompetitionGuessMatrix;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Members only, and gated by `leaderboard_details` rather than `leaderboard_view`:
 * this page shows TIPS, which the public board never does.
 */
#[Route('/zebricek/matice', name: 'leaderboard_matrix', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class GuessMatrixController extends AbstractController
{
    use ResolvesLeaderboardCompetition;

    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(self::competitionIdFromRequest($request));
        $this->denyAccessUnlessGranted(LeaderboardVoter::DETAILS, $competition);

        // Visibility (per-viewer entitlement + userless deadline) is resolved
        // inside the query via TipVisibilityGate — the manager/entitlement/deadline
        // logic no longer lives in the controller.
        $matrix = $this->queryBus->handle(new GetCompetitionGuessMatrix(
            competitionId: $competition->id,
            requestingUserId: $user->id,
        ));

        return $this->render('portal/leaderboard/matrix.html.twig', [
            'competition' => $competition,
            'matrix' => $matrix,
        ]);
    }
}
