<?php

declare(strict_types=1);

namespace App\Controller\Portal\MatchSource;

use App\Command\MarkMatchSourceFinished\MarkMatchSourceFinishedCommand;
use App\Repository\MatchSourceRepository;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/portal/turnaje/{id}/ukoncit', name: 'portal_match_source_finish', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class MarkMatchSourceFinishedController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(MatchSourceVoter::FINISH, $matchSource);

        if (!$this->isCsrfTokenValid('match_source_finish_'.$matchSource->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_match_source_detail', ['id' => $matchSource->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new MarkMatchSourceFinishedCommand(
            matchSourceId: $matchSource->id,
        ));

        $this->addFlash('success', 'Zdroj zápasů byl ukončen.');

        return $this->redirectToRoute('portal_match_source_detail', ['id' => $matchSource->id->toRfc4122()]);
    }
}
