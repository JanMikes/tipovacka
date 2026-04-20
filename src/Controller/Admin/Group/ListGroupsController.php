<?php

declare(strict_types=1);

namespace App\Controller\Admin\Group;

use App\Query\ListAdminGroups\ListAdminGroups;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/skupiny', name: 'admin_group_list', methods: ['GET'])]
final class ListGroupsController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $tournamentIdParam = $request->query->get('tournament');
        $tournamentId = null;

        if (is_string($tournamentIdParam) && '' !== $tournamentIdParam && Uuid::isValid($tournamentIdParam)) {
            $tournamentId = Uuid::fromString($tournamentIdParam);
        }

        $groups = $this->queryBus->handle(new ListAdminGroups(
            tournamentId: $tournamentId,
        ));

        return $this->render('admin/group/list.html.twig', [
            'groups' => $groups,
            'tournamentId' => $tournamentId,
        ]);
    }
}
