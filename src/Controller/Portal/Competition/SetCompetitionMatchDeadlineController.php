<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineCommand;
use App\Entity\User;
use App\Exception\CompetitionMatchDeadlineAfterKickoff;
use App\Exception\CompetitionMatchOpeningAfterDeadline;
use App\Exception\CompetitionMatchOpeningNoteWithoutTime;
use App\Exception\MatchNotInCompetition;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/souteze/{competitionId}/zapasy/{sportMatchId}/uzaverka',
    name: 'competition_sport_match_set_deadline',
    requirements: ['competitionId' => Requirement::UUID, 'sportMatchId' => Requirement::UUID],
    methods: ['POST'],
)]
#[IsGranted('ROLE_USER')]
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

        // The opening end is admin-only: for anyone else the fields are not
        // built at all, so a submitted `opensAt` has nothing to bind to — and
        // `changeOpening: false` then tells the handler to leave the stored
        // opening untouched instead of clearing it. The handler re-checks the
        // role, so a hand-crafted POST gets nowhere either.
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $formData = new CompetitionMatchDeadlineFormData();
        $form = $this->createForm(CompetitionMatchDeadlineFormType::class, $formData, [
            'with_opening' => $isAdmin,
        ]);
        $form->handleRequest($request);

        $redirect = $this->redirectToRoute('competition_sport_match_detail', [
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
                changeOpening: $isAdmin,
                opensAt: $formData->opensAt,
                openingNote: $formData->openingNote,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof CompetitionMatchDeadlineAfterKickoff
                || $previous instanceof MatchNotInCompetition
                || $previous instanceof CompetitionMatchOpeningAfterDeadline
                || $previous instanceof CompetitionMatchOpeningNoteWithoutTime
            ) {
                $this->addFlash('error', $previous->getMessage());

                return $redirect;
            }

            throw $e;
        }

        $this->addFlash(
            'success',
            null === $formData->deadline && null === $formData->opensAt
                ? 'Nastavení tipování zápasu bylo zrušeno.'
                : 'Nastavení tipování zápasu bylo uloženo.',
        );

        return $redirect;
    }
}
