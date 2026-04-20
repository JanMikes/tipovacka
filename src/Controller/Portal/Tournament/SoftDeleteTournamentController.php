<?php

declare(strict_types=1);

namespace App\Controller\Portal\Tournament;

use App\Command\SoftDeleteTournament\SoftDeleteTournamentCommand;
use App\Repository\TournamentRepository;
use App\Voter\TournamentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/portal/turnaje/{id}/smazat', name: 'portal_tournament_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class SoftDeleteTournamentController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $tournament = $this->tournamentRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(TournamentVoter::DELETE, $tournament);

        if (!$this->isCsrfTokenValid('tournament_delete_'.$tournament->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_tournament_detail', ['id' => $tournament->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new SoftDeleteTournamentCommand(
            tournamentId: $tournament->id,
        ));

        $this->addFlash('success', 'Turnaj byl smazán.');

        return $this->redirectToRoute('portal_dashboard');
    }
}
