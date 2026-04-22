<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\UpdateGroup\UpdateGroupCommand;
use App\Entity\User;
use App\Form\GroupFormData;
use App\Form\GroupFormType;
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
    '/portal/skupiny/{id}/upravit',
    name: 'portal_group_edit',
    requirements: ['id' => Requirement::UUID],
)]
final class UpdateGroupController extends AbstractController
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
        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);

        $formData = GroupFormData::fromGroup($group);
        $form = $this->createForm(GroupFormType::class, $formData, [
            'with_pin_disabled' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new UpdateGroupCommand(
                editorId: $user->id,
                groupId: $group->id,
                name: $formData->name,
                description: $formData->description ?: null,
                hideOthersTipsBeforeDeadline: $formData->hideOthersTipsBeforeDeadline,
                tipsDeadline: $formData->tipsDeadline,
            ));

            $this->addFlash('success', 'Skupina byla uložena.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
        }

        return $this->render('portal/group/edit.html.twig', [
            'form' => $form,
            'group' => $group,
        ]);
    }
}
