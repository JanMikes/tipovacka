<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\BulkImportSportMatches\BulkImportSportMatchesCommand;
use App\Entity\User;
use App\Repository\TournamentRepository;
use App\Service\SportMatch\SportMatchImportSession;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/turnaje/{tournamentId}/zapasy/import/potvrdit',
    name: 'portal_sport_match_import_commit',
    requirements: ['tournamentId' => Requirement::UUID],
    methods: ['POST'],
)]
final class BulkImportCommitController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly SportMatchImportSession $session,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $tournamentId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $tournament = $this->tournamentRepository->get(Uuid::fromString($tournamentId));
        $this->denyAccessUnlessGranted(SportMatchVoter::CREATE, $tournament);

        if (!$this->isCsrfTokenValid('sport_match_import_commit_'.$tournament->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_sport_match_import', ['tournamentId' => $tournament->id->toRfc4122()]);
        }

        $rows = $this->session->consume($tournament->id);

        if ([] === $rows) {
            $this->addFlash('error', 'Žádná data k importu. Nejprve nahrajte soubor.');

            return $this->redirectToRoute('portal_sport_match_import', ['tournamentId' => $tournament->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new BulkImportSportMatchesCommand(
            tournamentId: $tournament->id,
            editorId: $user->id,
            rows: $rows,
        ));

        $this->addFlash('success', sprintf('Importováno %d zápasů.', count($rows)));

        return $this->redirectToRoute('portal_tournament_detail', ['id' => $tournament->id->toRfc4122()]);
    }
}
