<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\RevokeGroupInvitation\RevokeGroupInvitationCommand;
use App\Entity\User;
use App\Exception\GroupInvitationAlreadyAccepted;
use App\Exception\NotAMember;
use App\Repository\GroupInvitationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/pozvanky/{invitationId}/zrusit',
    name: 'portal_invitation_revoke',
    requirements: ['invitationId' => Requirement::UUID],
    methods: ['POST'],
)]
final class RevokeInvitationController extends AbstractController
{
    public function __construct(
        private readonly GroupInvitationRepository $invitationRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $invitationId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $invitation = $this->invitationRepository->get(Uuid::fromString($invitationId));
        $groupId = $invitation->group->id->toRfc4122();

        if (!$this->isCsrfTokenValid('invitation_revoke_'.$invitation->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $groupId]);
        }

        try {
            $this->commandBus->dispatch(new RevokeGroupInvitationCommand(
                revokerId: $user->id,
                invitationId: $invitation->id,
            ));

            $this->addFlash('success', 'Pozvánka byla zrušena.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof NotAMember) {
                throw $this->createAccessDeniedException();
            }

            if ($inner instanceof GroupInvitationAlreadyAccepted) {
                $this->addFlash('warning', 'Pozvánka již byla přijata, nelze ji zrušit.');
            } else {
                throw $handlerFailed;
            }
        }

        return $this->redirectToRoute('portal_group_detail', ['id' => $groupId]);
    }
}
