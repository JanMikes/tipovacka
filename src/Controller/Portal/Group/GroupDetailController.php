<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Entity\User;
use App\Enum\UserRole;
use App\Query\GetGroupDetail\GetGroupDetail;
use App\Query\QueryBus;
use App\Repository\GroupRepository;
use App\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{id}',
    name: 'portal_group_detail',
    requirements: ['id' => Requirement::UUID],
)]
final class GroupDetailController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        $isAdmin = in_array(UserRole::ADMIN->value, $user->getRoles(), true);

        $detail = $this->queryBus->handle(new GetGroupDetail(
            groupId: $group->id,
            viewerId: $user->id,
            viewerIsAdmin: $isAdmin,
        ));

        return $this->render('portal/group/detail.html.twig', [
            'group' => $group,
            'detail' => $detail,
        ]);
    }
}
