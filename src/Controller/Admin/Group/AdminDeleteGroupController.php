<?php

declare(strict_types=1);

namespace App\Controller\Admin\Group;

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

#[Route('/admin/skupiny/{id}/smazat', name: 'admin_group_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class AdminDeleteGroupController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $group = $this->groupRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(GroupVoter::DELETE, $group);

        if (!$this->isCsrfTokenValid('admin_group_delete_'.$group->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('admin_group_list');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $this->commandBus->dispatch(new SoftDeleteGroupCommand(
            editorId: $admin->id,
            groupId: $group->id,
        ));

        $this->addFlash('success', 'Skupina byla smazána.');

        return $this->redirectToRoute('admin_group_list');
    }
}
