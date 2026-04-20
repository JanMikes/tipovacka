<?php

declare(strict_types=1);

namespace App\Controller\Portal\Tournament;

use App\Query\ListTournamentSportMatches\ListTournamentSportMatches;
use App\Query\QueryBus;
use App\Repository\TournamentRepository;
use App\Voter\TournamentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/portal/turnaje/{id}', name: 'portal_tournament_detail', requirements: ['id' => Requirement::UUID])]
final class TournamentDetailController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $tournament = $this->tournamentRepository->get(Uuid::fromString($id));

        $this->denyAccessUnlessGranted(TournamentVoter::VIEW, $tournament);

        $matches = $this->queryBus->handle(new ListTournamentSportMatches(tournamentId: $tournament->id));

        return $this->render('portal/tournament/detail.html.twig', [
            'tournament' => $tournament,
            'sport_matches' => $matches,
        ]);
    }
}
