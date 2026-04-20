<?php

declare(strict_types=1);

namespace App\Controller\Admin\Tournament;

use App\Query\ListAdminTournaments\ListAdminTournaments;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/turnaje', name: 'admin_tournament_list', methods: ['GET'])]
final class ListTournamentsController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(): Response
    {
        $tournaments = $this->queryBus->handle(new ListAdminTournaments());

        return $this->render('admin/tournament/list.html.twig', [
            'tournaments' => $tournaments,
        ]);
    }
}
