<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\User;
use App\Query\ListActivePublicMatchSources\ListActivePublicMatchSources;
use App\Query\ListMyAccessiblePrivateMatchSources\ListMyAccessiblePrivateMatchSources;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/turnaje', name: 'public_match_sources_list', methods: ['GET'])]
final class PublicMatchSourcesListController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(): Response
    {
        $publicMatchSources = $this->queryBus->handle(new ListActivePublicMatchSources());

        $user = $this->getUser();
        $userHasCompetitions = false;
        $privateMatchSources = [];
        if ($user instanceof User && $user->isVerified) {
            $userHasCompetitions = [] !== $this->queryBus->handle(new ListMyCompetitions(userId: $user->id));
            $privateMatchSources = $this->queryBus->handle(new ListMyAccessiblePrivateMatchSources(userId: $user->id));
        }

        return $this->render('public/match_sources_list.html.twig', [
            'public_match_sources' => $publicMatchSources,
            'private_match_sources' => $privateMatchSources,
            'user_has_competitions' => $userHasCompetitions,
        ]);
    }
}
