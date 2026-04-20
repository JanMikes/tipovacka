<?php

declare(strict_types=1);

namespace App\Controller\Admin\Tournament;

use App\Command\UpdateTournament\UpdateTournamentCommand;
use App\Form\TournamentFormData;
use App\Form\TournamentFormType;
use App\Repository\TournamentRepository;
use App\Voter\TournamentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/turnaje/{id}/upravit', name: 'admin_tournament_edit', requirements: ['id' => Requirement::UUID])]
final class AdminUpdateTournamentController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $tournament = $this->tournamentRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(TournamentVoter::EDIT, $tournament);

        $formData = TournamentFormData::fromTournament($tournament);
        $form = $this->createForm(TournamentFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new UpdateTournamentCommand(
                tournamentId: $tournament->id,
                name: $formData->name,
                description: $formData->description ?: null,
                startAt: $formData->startAt,
                endAt: $formData->endAt,
            ));

            $this->addFlash('success', 'Turnaj byl uložen.');

            return $this->redirectToRoute('admin_tournament_list');
        }

        return $this->render('admin/tournament/edit.html.twig', [
            'form' => $form,
            'tournament' => $tournament,
        ]);
    }
}
