<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\RequestToJoinGroup\RequestToJoinGroupCommand;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedTournament;
use App\Exception\DuplicatePendingJoinRequest;
use App\Exception\JoinRequestNotAllowed;
use App\Repository\GroupRepository;
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
    '/portal/skupiny/{id}/pozadat-o-pripojeni',
    name: 'portal_group_request_join',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class RequestJoinController extends AbstractController
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
        $this->denyAccessUnlessGranted(GroupVoter::REQUEST_JOIN, $group);

        if (!$this->isCsrfTokenValid('group_request_join_'.$group->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_dashboard');
        }

        try {
            $this->commandBus->dispatch(new RequestToJoinGroupCommand(
                userId: $user->id,
                groupId: $group->id,
            ));

            $this->addFlash('success', 'Žádost o připojení byla odeslána.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof JoinRequestNotAllowed) {
                throw $this->createAccessDeniedException();
            }

            if ($inner instanceof AlreadyMember) {
                $this->addFlash('info', 'Ve skupině již jsi.');
            } elseif ($inner instanceof CannotJoinFinishedTournament) {
                $this->addFlash('warning', 'Turnaj této skupiny je již ukončen.');
            } elseif ($inner instanceof DuplicatePendingJoinRequest) {
                $this->addFlash('info', 'Žádost o připojení již čeká na vyřízení.');
            } else {
                throw $handlerFailed;
            }
        }

        return $this->redirectToRoute('portal_dashboard');
    }
}
