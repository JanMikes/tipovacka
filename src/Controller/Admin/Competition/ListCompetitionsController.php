<?php

declare(strict_types=1);

namespace App\Controller\Admin\Competition;

use App\Query\ListAdminCompetitions\ListAdminCompetitions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/souteze', name: 'admin_competition_list', methods: ['GET'])]
final class ListCompetitionsController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $matchSourceIdParam = $request->query->get('match_source');
        $matchSourceId = null;

        if (is_string($matchSourceIdParam) && '' !== $matchSourceIdParam && Uuid::isValid($matchSourceIdParam)) {
            $matchSourceId = Uuid::fromString($matchSourceIdParam);
        }

        $competitions = $this->queryBus->handle(new ListAdminCompetitions(
            matchSourceId: $matchSourceId,
        ));

        return $this->render('admin/competition/list.html.twig', [
            'competitions' => $competitions,
            'matchSourceId' => $matchSourceId,
        ]);
    }
}
