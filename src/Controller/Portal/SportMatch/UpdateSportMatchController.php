<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\UpdateSportMatch\UpdateSportMatchCommand;
use App\Entity\User;
use App\Form\SportMatchFormData;
use App\Form\SportMatchFormType;
use App\Repository\SportMatchRepository;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/zapasy/{id}/upravit',
    name: 'portal_sport_match_edit',
    requirements: ['id' => Requirement::UUID],
)]
final class UpdateSportMatchController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::EDIT, $sportMatch);

        $formData = SportMatchFormData::fromSportMatch($sportMatch);
        $form = $this->createForm(SportMatchFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new UpdateSportMatchCommand(
                sportMatchId: $sportMatch->id,
                editorId: $user->id,
                homeTeam: $formData->homeTeam,
                awayTeam: $formData->awayTeam,
                kickoffAt: $formData->kickoffAt,
                venue: $formData->venue ?: null,
            ));

            $this->addFlash('success', 'Zápas byl uložen.');

            return $this->redirectToRoute('portal_sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
        }

        return $this->render('portal/sport_match/form.html.twig', [
            'form' => $form,
            'tournament' => $sportMatch->tournament,
            'sport_match' => $sportMatch,
            'mode' => 'edit',
        ]);
    }
}
