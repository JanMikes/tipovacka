<?php

declare(strict_types=1);

namespace App\Controller\Admin\SportMatch;

use App\Query\ListMatchSourceSportMatches\ListMatchSourceSportMatches;
use App\Query\QueryBus;
use App\Repository\MatchSourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/turnaje/{matchSourceId}/zapasy', name: 'admin_sport_match_list', requirements: ['matchSourceId' => Requirement::UUID], methods: ['GET'])]
final class ListMatchesController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $matchSourceId): Response
    {
        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($matchSourceId));

        $matches = $this->queryBus->handle(new ListMatchSourceSportMatches(
            matchSourceId: $matchSource->id,
        ));

        return $this->render('admin/sport_match/list.html.twig', [
            'match_source' => $matchSource,
            'matches' => $matches,
        ]);
    }
}
