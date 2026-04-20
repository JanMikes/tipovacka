<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\RegenerateShareableLink\RegenerateShareableLinkCommand;
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
    '/portal/skupiny/{id}/odkaz/novy',
    name: 'portal_group_link_regenerate',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class RegenerateShareableLinkController extends AbstractController
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

        if (!$this->isCsrfTokenValid('group_link_regenerate_'.$group->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new RegenerateShareableLinkCommand(
            ownerId: $user->id,
            groupId: $group->id,
        ));

        $this->addFlash('success', 'Pozvánkový odkaz byl obnoven.');

        return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
    }
}
