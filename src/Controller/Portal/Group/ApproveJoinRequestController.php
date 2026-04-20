<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\ApproveJoinRequest\ApproveJoinRequestCommand;
use App\Entity\User;
use App\Exception\CannotJoinFinishedTournament;
use App\Exception\GroupJoinRequestAlreadyDecided;
use App\Repository\GroupJoinRequestRepository;
use App\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/zadosti/{requestId}/schvalit',
    name: 'portal_join_request_approve',
    requirements: ['requestId' => Requirement::UUID],
    methods: ['POST'],
)]
final class ApproveJoinRequestController extends AbstractController
{
    public function __construct(
        private readonly GroupJoinRequestRepository $joinRequestRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $requestId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $joinRequest = $this->joinRequestRepository->get(Uuid::fromString($requestId));
        $this->denyAccessUnlessGranted(GroupVoter::MANAGE_MEMBERS, $joinRequest->group);

        $groupId = $joinRequest->group->id->toRfc4122();

        if (!$this->isCsrfTokenValid('join_request_approve_'.$joinRequest->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $groupId]);
        }

        try {
            $this->commandBus->dispatch(new ApproveJoinRequestCommand(
                ownerId: $user->id,
                requestId: $joinRequest->id,
            ));

            $this->addFlash('success', 'Žádost byla schválena a uživatel byl přidán do skupiny.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof GroupJoinRequestAlreadyDecided) {
                $this->addFlash('info', 'O žádosti již bylo rozhodnuto.');
            } elseif ($inner instanceof CannotJoinFinishedTournament) {
                $this->addFlash('warning', 'Turnaj této skupiny je již ukončen.');
            } else {
                throw $handlerFailed;
            }
        }

        return $this->redirectToRoute('portal_group_detail', ['id' => $groupId]);
    }
}
