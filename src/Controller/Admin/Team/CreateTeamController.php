<?php

declare(strict_types=1);

namespace App\Controller\Admin\Team;

use App\Command\CreateTeam\CreateTeamCommand;
use App\Exception\TeamLogoNotProcessable;
use App\Form\TeamFormData;
use App\Form\TeamFormType;
use App\Repository\TeamRepository;
use App\Service\Team\TeamLogoStorage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tymy/vytvorit', name: 'admin_team_create')]
final class CreateTeamController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly TeamRepository $teamRepository,
        private readonly TeamLogoStorage $logoStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $formData = new TeamFormData();
        $form = $this->createForm(TeamFormType::class, $formData, ['with_sport' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $formData->sport);

            // The directory holds ONE row per club per sport (a partial unique index,
            // matched case-insensitively like TeamResolver does) — a duplicate would
            // otherwise surface as a 500 from the index. Checked before the logo is stored.
            $existing = $this->teamRepository->findGlobalByName($formData->sport->id, $formData->name);

            if (null !== $existing) {
                $form->get('name')->addError(new FormError(sprintf(
                    'V adresáři už je tým „%s“ (%s). Upravte ten existující, nebo zvolte jiný název.',
                    $existing->name,
                    $formData->sport->name,
                )));
            } else {
                try {
                    $logo = null !== $formData->logoFile ? $this->logoStorage->store($formData->logoFile) : null;

                    $this->commandBus->dispatch(new CreateTeamCommand(
                        sportId: $formData->sport->id,
                        name: $formData->name,
                        shortName: $formData->shortName ?: null,
                        country: $formData->country ?: null,
                        brandColor: $formData->brandColor ?: null,
                        logo: $logo,
                    ));

                    $this->addFlash('success', 'Tým byl přidán do adresáře.');

                    return $this->redirectToRoute('admin_team_list');
                } catch (TeamLogoNotProcessable $e) {
                    // Passed the Image constraint, yet GD could not decode/convert/store it.
                    $this->logger->warning('Team logo upload could not be processed.', ['exception' => $e]);
                    $form->get('logoFile')->addError(new FormError(TeamFormData::LOGO_NOT_PROCESSABLE_MESSAGE));
                }
            }
        }

        return $this->render('admin/team/form.html.twig', [
            'form' => $form,
            'mode' => 'create',
        ]);
    }
}
