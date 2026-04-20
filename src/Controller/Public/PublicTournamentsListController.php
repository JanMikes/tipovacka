<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Query\ListActivePublicTournaments\ListActivePublicTournaments;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/turnaje', name: 'public_tournaments_list', methods: ['GET'])]
final class PublicTournamentsListController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(): Response
    {
        $tournaments = $this->queryBus->handle(new ListActivePublicTournaments());

        return $this->render('public/tournaments_list.html.twig', [
            'tournaments' => $tournaments,
        ]);
    }
}
