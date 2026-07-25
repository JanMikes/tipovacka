<?php

declare(strict_types=1);

namespace App\Controller\Admin\Team;

use App\Query\ListGlobalTeams\ListGlobalTeams;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tymy', name: 'admin_team_list', methods: ['GET'])]
final class ListTeamsController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(): Response
    {
        $teams = $this->queryBus->handle(new ListGlobalTeams());

        return $this->render('admin/team/list.html.twig', [
            'teams' => $teams,
        ]);
    }
}
