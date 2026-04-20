<?php

declare(strict_types=1);

namespace App\Controller\Admin\SportMatch;

use App\Query\ListTournamentSportMatches\ListTournamentSportMatches;
use App\Query\QueryBus;
use App\Repository\TournamentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/turnaje/{tournamentId}/zapasy', name: 'admin_sport_match_list', requirements: ['tournamentId' => Requirement::UUID], methods: ['GET'])]
final class ListMatchesController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $tournamentId): Response
    {
        $tournament = $this->tournamentRepository->get(Uuid::fromString($tournamentId));

        $matches = $this->queryBus->handle(new ListTournamentSportMatches(
            tournamentId: $tournament->id,
        ));

        return $this->render('admin/sport_match/list.html.twig', [
            'tournament' => $tournament,
            'matches' => $matches,
        ]);
    }
}
