<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\SendGroupInvitation\SendGroupInvitationCommand;
use App\Entity\User;
use App\Form\SendInvitationFormData;
use App\Form\SendInvitationFormType;
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
    '/portal/skupiny/{id}/pozvanky/odeslat',
    name: 'portal_group_invitation_send',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class SendInvitationController extends AbstractController
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
        $this->denyAccessUnlessGranted(GroupVoter::INVITE_MEMBER, $group);

        $formData = new SendInvitationFormData();
        $form = $this->createForm(SendInvitationFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new SendGroupInvitationCommand(
                inviterId: $user->id,
                groupId: $group->id,
                email: $formData->email,
            ));

            $this->addFlash('success', 'Pozvánka byla odeslána.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
        }

        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
    }
}
