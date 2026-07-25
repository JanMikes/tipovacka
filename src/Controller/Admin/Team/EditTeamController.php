<?php

declare(strict_types=1);

namespace App\Controller\Admin\Team;

use App\Command\UpdateTeam\UpdateTeamCommand;
use App\Form\TeamFormData;
use App\Form\TeamFormType;
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/tymy/{id}/upravit', name: 'admin_team_edit', requirements: ['id' => Requirement::UUID])]
final class EditTeamController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly TeamRepository $teamRepository,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $team = $this->teamRepository->get(Uuid::fromString($id));

        $formData = TeamFormData::fromTeam($team);
        $form = $this->createForm(TeamFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new UpdateTeamCommand(
                teamId: $team->id,
                name: $formData->name,
                shortName: $formData->shortName ?: null,
                country: $formData->country ?: null,
                brandColor: $formData->brandColor ?: null,
            ));

            $this->addFlash('success', 'Tým byl upraven.');

            return $this->redirectToRoute('admin_team_list');
        }

        return $this->render('admin/team/form.html.twig', [
            'form' => $form,
            'mode' => 'edit',
            'team' => $team,
        ]);
    }
}
