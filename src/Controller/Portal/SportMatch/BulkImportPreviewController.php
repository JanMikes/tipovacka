<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Exception\SportMatchImportFailed;
use App\Form\ImportSportMatchesFormData;
use App\Form\ImportSportMatchesFormType;
use App\Repository\TournamentRepository;
use App\Service\SportMatch\SportMatchImporter;
use App\Service\SportMatch\SportMatchImportSession;
use App\Voter\SportMatchVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/turnaje/{tournamentId}/zapasy/import',
    name: 'portal_sport_match_import',
    requirements: ['tournamentId' => Requirement::UUID],
)]
final class BulkImportPreviewController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly SportMatchImporter $importer,
        private readonly SportMatchImportSession $session,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $tournamentId): Response
    {
        $tournament = $this->tournamentRepository->get(Uuid::fromString($tournamentId));
        $this->denyAccessUnlessGranted(SportMatchVoter::CREATE, $tournament);

        $formData = new ImportSportMatchesFormData();
        $form = $this->createForm(ImportSportMatchesFormType::class, $formData);
        $form->handleRequest($request);

        $preview = null;

        if ($form->isSubmitted() && $form->isValid() && null !== $formData->file) {
            try {
                $now = \DateTimeImmutable::createFromInterface($this->clock->now());
                $preview = $this->importer->preview($formData->file, $tournament, $now);

                if ([] === $preview->errors && [] !== $preview->validRows) {
                    $this->session->store($tournament->id, $preview->validRows);
                } else {
                    $this->session->clear($tournament->id);
                }
            } catch (SportMatchImportFailed $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('portal_sport_match_import', ['tournamentId' => $tournament->id->toRfc4122()]);
            }
        }

        return $this->render('portal/sport_match/import.html.twig', [
            'form' => $form,
            'tournament' => $tournament,
            'preview' => $preview,
        ]);
    }
}
