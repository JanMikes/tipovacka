<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\CreateAnonymousMember\CreateAnonymousMemberCommand;
use App\Entity\User;
use App\Form\AnonymousMemberFormData;
use App\Form\AnonymousMemberFormType;
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
    '/portal/skupiny/{id}/clenove/bez-emailu',
    name: 'portal_group_add_anonymous_member',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET', 'POST'],
)]
final class AddAnonymousMemberController extends AbstractController
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
        $this->denyAccessUnlessGranted(GroupVoter::MANAGE_MEMBERS, $group);

        $formData = new AnonymousMemberFormData();
        $form = $this->createForm(AnonymousMemberFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new CreateAnonymousMemberCommand(
                groupId: $group->id,
                actorId: $user->id,
                firstName: trim($formData->firstName),
                lastName: trim($formData->lastName),
                nickname: $formData->nickname,
            ));

            $this->addFlash('success', 'Tipující byl přidán do skupiny.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
        }

        return $this->render('portal/group/add_anonymous_member.html.twig', [
            'group' => $group,
            'form' => $form,
        ]);
    }
}
