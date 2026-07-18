<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\BulkImportSportMatches\BulkImportSportMatchesCommand;
use App\Entity\User;
use App\Repository\MatchSourceRepository;
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
    '/portal/turnaje/{matchSourceId}/zapasy/import/potvrdit',
    name: 'portal_sport_match_import_commit',
    requirements: ['matchSourceId' => Requirement::UUID],
    methods: ['POST'],
)]
final class BulkImportCommitController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly SportMatchImportSession $session,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $matchSourceId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($matchSourceId));
        $this->denyAccessUnlessGranted(SportMatchVoter::CREATE, $matchSource);

        if (!$this->isCsrfTokenValid('sport_match_import_commit_'.$matchSource->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_sport_match_import', ['matchSourceId' => $matchSource->id->toRfc4122()]);
        }

        $rows = $this->session->consume($matchSource->id);

        if ([] === $rows) {
            $this->addFlash('error', 'Žádná data k importu. Nejprve nahrajte soubor.');

            return $this->redirectToRoute('portal_sport_match_import', ['matchSourceId' => $matchSource->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new BulkImportSportMatchesCommand(
            matchSourceId: $matchSource->id,
            editorId: $user->id,
            rows: $rows,
        ));

        $this->addFlash('success', sprintf('Importováno %d zápasů.', count($rows)));

        return $this->redirectToRoute('portal_match_source_detail', ['id' => $matchSource->id->toRfc4122()]);
    }
}
