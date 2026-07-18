<?php

declare(strict_types=1);

namespace App\Controller\Admin\MatchSource;

use App\Query\ListAdminMatchSources\ListAdminMatchSources;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/turnaje', name: 'admin_match_source_list', methods: ['GET'])]
final class ListMatchSourcesController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(): Response
    {
        $matchSources = $this->queryBus->handle(new ListAdminMatchSources());

        return $this->render('admin/match_source/list.html.twig', [
            'match_sources' => $matchSources,
        ]);
    }
}
