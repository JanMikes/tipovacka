<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Entity\User;
use App\Query\GetCompetitionGuessMatrix\GetCompetitionGuessMatrix;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{competitionId}/zebricek/matice',
    name: 'portal_competition_leaderboard_matrix',
    requirements: ['competitionId' => Requirement::UUID],
)]
final class GuessMatrixController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $competitionId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($competitionId));
        $this->denyAccessUnlessGranted(LeaderboardVoter::VIEW, $competition);

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
