<?php

declare(strict_types=1);

namespace App\Controller\Admin\MatchSource;

use App\Command\UpdateMatchSource\UpdateMatchSourceCommand;
use App\Form\MatchSourceFormData;
use App\Form\MatchSourceFormType;
use App\Repository\MatchSourceRepository;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/turnaje/{id}/upravit', name: 'admin_match_source_edit', requirements: ['id' => Requirement::UUID])]
final class AdminUpdateMatchSourceController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(MatchSourceVoter::EDIT, $matchSource);

        $formData = MatchSourceFormData::fromMatchSource($matchSource);
        $form = $this->createForm(MatchSourceFormType::class, $formData, [
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new UpdateMatchSourceCommand(
                matchSourceId: $matchSource->id,
                name: $formData->name,
                description: $formData->description ?: null,
                startAt: $formData->startAt,
                endAt: $formData->endAt,
            ));

            $this->addFlash('success', 'Zdroj zápasů byl uložen.');

            return $this->redirectToRoute('admin_match_source_list');
        }

        return $this->render('admin/match_source/edit.html.twig', [
            'form' => $form,
            'match_source' => $matchSource,
        ]);
    }
}
