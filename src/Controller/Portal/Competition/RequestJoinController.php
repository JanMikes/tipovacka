<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\RequestToJoinCompetition\RequestToJoinCompetitionCommand;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Exception\DuplicatePendingJoinRequest;
use App\Exception\JoinRequestNotAllowed;
use App\Repository\CompetitionRepository;
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
    '/portal/souteze/{id}/pozadat-o-pripojeni',
    name: 'portal_competition_request_join',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class RequestJoinController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::REQUEST_JOIN, $competition);

        if (!$this->isCsrfTokenValid('competition_request_join_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_dashboard');
        }

        try {
            $this->commandBus->dispatch(new RequestToJoinCompetitionCommand(
                userId: $user->id,
                competitionId: $competition->id,
            ));

            $this->addFlash('success', 'Žádost o připojení byla odeslána.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof JoinRequestNotAllowed) {
                throw $this->createAccessDeniedException();
            }

            if ($inner instanceof AlreadyMember) {
                $this->addFlash('info', 'V soutěži již jsi.');
            } elseif ($inner instanceof CannotJoinFinishedMatchSource) {
                $this->addFlash('warning', 'Zdroj zápasů této soutěže je již ukončen.');
            } elseif ($inner instanceof DuplicatePendingJoinRequest) {
                $this->addFlash('info', 'Žádost o připojení již čeká na vyřízení.');
            } else {
                throw $handlerFailed;
            }
        }

        return $this->redirectToRoute('portal_dashboard');
    }
}
