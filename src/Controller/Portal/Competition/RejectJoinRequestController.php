<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\RejectJoinRequest\RejectJoinRequestCommand;
use App\Entity\User;
use App\Exception\CompetitionJoinRequestAlreadyDecided;
use App\Repository\CompetitionJoinRequestRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/zadosti/{requestId}/zamitnout',
    name: 'portal_join_request_reject',
    requirements: ['requestId' => Requirement::UUID],
    methods: ['POST'],
)]
final class RejectJoinRequestController extends AbstractController
{
    public function __construct(
        private readonly CompetitionJoinRequestRepository $joinRequestRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $requestId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $joinRequest = $this->joinRequestRepository->get(Uuid::fromString($requestId));
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_MEMBERS, $joinRequest->competition);

        $competitionId = $joinRequest->competition->id->toRfc4122();

        if (!$this->isCsrfTokenValid('join_request_reject_'.$joinRequest->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competitionId]);
        }

        try {
            $this->commandBus->dispatch(new RejectJoinRequestCommand(
                ownerId: $user->id,
                requestId: $joinRequest->id,
            ));

            $this->addFlash('success', 'Žádost byla zamítnuta.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof CompetitionJoinRequestAlreadyDecided) {
                $this->addFlash('info', 'O žádosti již bylo rozhodnuto.');
            } else {
                throw $handlerFailed;
            }
        }

        return $this->redirectToRoute('portal_competition_detail', ['id' => $competitionId]);
    }
}
