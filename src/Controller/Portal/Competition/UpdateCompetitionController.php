<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\UpdateCompetition\UpdateCompetitionCommand;
use App\Entity\User;
use App\Form\CompetitionFormData;
use App\Form\CompetitionFormType;
use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}/upravit',
    name: 'portal_competition_edit',
    requirements: ['id' => Requirement::UUID],
)]
final class UpdateCompetitionController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        $formData = CompetitionFormData::fromCompetition($competition);
        $form = $this->createForm(CompetitionFormType::class, $formData, [
            'with_pin_disabled' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new UpdateCompetitionCommand(
                editorId: $user->id,
                competitionId: $competition->id,
                name: $formData->name,
                description: $formData->description ?: null,
                hideOthersTipsBeforeDeadline: $formData->hideOthersTipsBeforeDeadline,
                tipsDeadline: $formData->tipsDeadline,
            ));

            $this->addFlash('success', 'Soutěž byla uložena.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        return $this->render('portal/competition/edit.html.twig', [
            'form' => $form,
            'competition' => $competition,
        ]);
    }
}
