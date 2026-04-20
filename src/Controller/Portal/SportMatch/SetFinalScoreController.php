<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Entity\User;
use App\Form\SetFinalScoreFormData;
use App\Form\SetFinalScoreFormType;
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
    '/portal/zapasy/{id}/skore',
    name: 'portal_sport_match_set_score',
    requirements: ['id' => Requirement::UUID],
)]
final class SetFinalScoreController extends AbstractController
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
        $this->denyAccessUnlessGranted(SportMatchVoter::SET_SCORE, $sportMatch);

        $formData = new SetFinalScoreFormData();
        $formData->homeScore = $sportMatch->homeScore;
        $formData->awayScore = $sportMatch->awayScore;

        $form = $this->createForm(SetFinalScoreFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $formData->homeScore);
            \assert(null !== $formData->awayScore);

            $this->commandBus->dispatch(new SetSportMatchFinalScoreCommand(
                sportMatchId: $sportMatch->id,
                editorId: $user->id,
                homeScore: $formData->homeScore,
                awayScore: $formData->awayScore,
            ));

            $this->addFlash('success', 'Skóre bylo uloženo.');

            return $this->redirectToRoute('portal_sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
        }

        return $this->render('portal/sport_match/set_score.html.twig', [
            'form' => $form,
            'sport_match' => $sportMatch,
        ]);
    }
}
