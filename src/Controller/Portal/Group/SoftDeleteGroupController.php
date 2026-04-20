<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\SoftDeleteGroup\SoftDeleteGroupCommand;
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
    '/portal/skupiny/{id}/smazat',
    name: 'portal_group_delete',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class SoftDeleteGroupController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(GroupVoter::DELETE, $group);

        if (!$this->isCsrfTokenValid('group_delete_'.$group->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new SoftDeleteGroupCommand(
            editorId: $user->id,
            groupId: $group->id,
        ));

        $this->addFlash('success', 'Skupina byla smazána.');

        return $this->redirectToRoute('portal_dashboard');
    }
}
