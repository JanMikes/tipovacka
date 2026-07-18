<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineCommand;
use App\Entity\User;
use App\Exception\CompetitionMatchDeadlineAfterKickoff;
use App\Form\CompetitionMatchDeadlineFormData;
use App\Form\CompetitionMatchDeadlineFormType;
use App\Repository\CompetitionRepository;
use App\Repository\SportMatchRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{competitionId}/zapasy/{sportMatchId}/uzaverka',
    name: 'portal_competition_sport_match_set_deadline',
    requirements: ['competitionId' => Requirement::UUID, 'sportMatchId' => Requirement::UUID],
    methods: ['POST'],
)]
final class SetCompetitionMatchDeadlineController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $competitionId, string $sportMatchId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($competitionId));
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchId));

        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        $formData = new CompetitionMatchDeadlineFormData();
        $form = $this->createForm(CompetitionMatchDeadlineFormType::class, $formData);
        $form->handleRequest($request);

        $redirect = $this->redirectToRoute('portal_competition_sport_match_guesses', [
            'competitionId' => $competition->id->toRfc4122(),
            'sportMatchId' => $sportMatch->id->toRfc4122(),
        ]);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Neplatný vstup uzávěrky.');

            return $redirect;
        }

        try {
            $this->commandBus->dispatch(new SetCompetitionMatchDeadlineCommand(
                editorId: $user->id,
                competitionId: $competition->id,
                sportMatchId: $sportMatch->id,
                deadline: $formData->deadline,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof CompetitionMatchDeadlineAfterKickoff) {
                $this->addFlash('error', $previous->getMessage());

                return $redirect;
            }

            throw $e;
        }

        $this->addFlash(
            'success',
            null === $formData->deadline ? 'Uzávěrka byla zrušena.' : 'Uzávěrka byla uložena.',
        );

        return $redirect;
    }
}
