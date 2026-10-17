<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Exception\SportMatchImportFailed;
use App\Form\ImportSportMatchesFormData;
use App\Form\ImportSportMatchesFormType;
use App\Repository\MatchSourceRepository;
use App\Service\Competition\ScopeReturn;
use App\Service\SportMatch\SportMatchImporter;
use App\Service\SportMatch\SportMatchImportSession;
use App\Voter\SportMatchVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/turnaje/{matchSourceId}/zapasy/import',
    name: 'sport_match_import',
    requirements: ['matchSourceId' => Requirement::UUID],
)]
#[IsGranted('ROLE_USER')]
final class BulkImportPreviewController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly ScopeReturn $scopeReturn,
        private readonly SportMatchImporter $importer,
        private readonly SportMatchImportSession $session,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $matchSourceId): Response
    {
        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($matchSourceId));
        $this->denyAccessUnlessGranted(SportMatchVoter::CREATE, $matchSource);

        $formData = new ImportSportMatchesFormData();
        $form = $this->createForm(ImportSportMatchesFormType::class, $formData);
        $form->handleRequest($request);

        $preview = null;

        if ($form->isSubmitted() && $form->isValid() && null !== $formData->file) {
            try {
                $now = \DateTimeImmutable::createFromInterface($this->clock->now());
                $preview = $this->importer->preview($formData->file, $matchSource, $now);

                if ([] === $preview->errors && [] !== $preview->validRows) {
                    $this->session->store($matchSource->id, $preview->validRows);
                } else {
                    $this->session->clear($matchSource->id);
                }
            } catch (SportMatchImportFailed $e) {
                // On the file field itself (re-rendered with 422) — a redirect would
                // drop the „soutez" return path and show the reason a screen away.
                $form->get('file')->addError(new FormError($e->getMessage()));
                $this->session->clear($matchSource->id);
            }
        }

        // A preview with bad rows is a rejected import: 422, like any rejected form.
        $rejected = null !== $preview && [] !== $preview->errors;

        return $this->render('portal/sport_match/import.html.twig', [
            'form' => $form,
            'match_source' => $matchSource,
            'preview' => $preview,
            // „Přišel jsem sem ze soutěže" — carried through upload → preview → commit.
            'scope_competition_id' => $this->scopeReturn->competitionId($request),
        ], $rejected ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }
}
