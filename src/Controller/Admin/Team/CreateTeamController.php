<?php

declare(strict_types=1);

namespace App\Controller\Admin\Team;

use App\Command\CreateTeam\CreateTeamCommand;
use App\Form\TeamFormData;
use App\Form\TeamFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tymy/vytvorit', name: 'admin_team_create')]
final class CreateTeamController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $formData = new TeamFormData();
        $form = $this->createForm(TeamFormType::class, $formData, ['with_sport' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $formData->sport);

            $this->commandBus->dispatch(new CreateTeamCommand(
                sportId: $formData->sport->id,
                name: $formData->name,
                shortName: $formData->shortName ?: null,
                country: $formData->country ?: null,
                brandColor: $formData->brandColor ?: null,
            ));

            $this->addFlash('success', 'Tým byl přidán do adresáře.');

            return $this->redirectToRoute('admin_team_list');
        }

        return $this->render('admin/team/form.html.twig', [
            'form' => $form,
            'mode' => 'create',
        ]);
    }
}
