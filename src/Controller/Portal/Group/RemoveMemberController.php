<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\RemoveMember\RemoveMemberCommand;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{id}/clenove/{userId}/odebrat',
    name: 'portal_group_remove_member',
    requirements: ['id' => Requirement::UUID, 'userId' => Requirement::UUID],
    methods: ['POST'],
)]
final class RemoveMemberController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id, string $userId): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(GroupVoter::MANAGE_MEMBERS, $group);

        $csrfId = 'group_remove_member_'.$group->id->toRfc4122().'_'.$userId;
        if (!$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new RemoveMemberCommand(
            ownerId: $currentUser->id,
            groupId: $group->id,
            targetUserId: Uuid::fromString($userId),
        ));

        $this->addFlash('success', 'Člen byl odebrán ze skupiny.');

        return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
    }
}
