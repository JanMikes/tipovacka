<?php

declare(strict_types=1);

namespace App\Controller\Portal\MatchSource;

use App\Query\ListCompetitionsForMatchSource\ListCompetitionsForMatchSource;
use App\Query\ListMatchSourceSportMatches\ListMatchSourceSportMatches;
use App\Query\QueryBus;
use App\Repository\MatchSourceRepository;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/portal/turnaje/{id}', name: 'portal_match_source_detail', requirements: ['id' => Requirement::UUID])]
final class MatchSourceDetailController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($id));

        $this->denyAccessUnlessGranted(MatchSourceVoter::VIEW, $matchSource);

        $matches = $this->queryBus->handle(new ListMatchSourceSportMatches(matchSourceId: $matchSource->id));
        $competitions = $this->queryBus->handle(new ListCompetitionsForMatchSource(matchSourceId: $matchSource->id));

        return $this->render('portal/match_source/detail.html.twig', [
            'match_source' => $matchSource,
            'sport_matches' => $matches,
            'competitions' => $competitions,
        ]);
    }
}
