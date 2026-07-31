<?php

declare(strict_types=1);

namespace App\Controller\Admin\Team;

use App\Command\UpdateTeam\UpdateTeamCommand;
use App\Form\TeamFormData;
use App\Form\TeamFormType;
use App\Repository\TeamRepository;
use App\Service\Team\TeamLogoStorage;
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
        private readonly TeamLogoStorage $logoStorage,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $team = $this->teamRepository->get(Uuid::fromString($id));

        $formData = TeamFormData::fromTeam($team);
        $form = $this->createForm(TeamFormType::class, $formData, ['with_logo_removal' => null !== $team->logo]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $previousLogo = $team->logo;
            $newLogo = null !== $formData->logoFile ? $this->logoStorage->store($formData->logoFile) : null;

            $this->commandBus->dispatch(new UpdateTeamCommand(
                teamId: $team->id,
                name: $formData->name,
                shortName: $formData->shortName ?: null,
                country: $formData->country ?: null,
                brandColor: $formData->brandColor ?: null,
                logo: $newLogo,
                removeLogo: $formData->removeLogo,
            ));

            // The command committed, so the replaced file is now unreferenced.
            if (null !== $newLogo || $formData->removeLogo) {
                $this->logoStorage->remove($previousLogo);
            }

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
