<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\UpdateCompetitionMatchSelection\UpdateCompetitionMatchSelectionCommand;
use App\Entity\SportMatch;
use App\Enum\CompetitionMatchSelectionMode;
use App\Repository\CompetitionMatchSelectionRepository;
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
    '/portal/souteze/{id}/zapasy-vyber',
    name: 'portal_competition_match_selection',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET', 'POST'],
)]
final class CompetitionMatchSelectionController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly CompetitionMatchSelectionRepository $selectionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        if (CompetitionMatchSelectionMode::Subset !== $competition->selectionMode) {
            $this->addFlash('info', 'Tato soutěž zahrnuje všechny zápasy zdroje — výběr zápasů se nespravuje.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->get('_token');

            if (!\is_string($csrfToken) || !$this->isCsrfTokenValid('competition_match_selection_'.$competition->id->toRfc4122(), $csrfToken)) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $selectedMatchIds = [];

            foreach ($request->request->all('matches') as $matchId) {
                if (\is_string($matchId) && Uuid::isValid($matchId)) {
                    $selectedMatchIds[] = Uuid::fromString($matchId);
                }
            }

            if (0 === count($selectedMatchIds)) {
                $this->addFlash('error', 'Vyberte prosím alespoň jeden zápas.');
            } else {
                try {
                    $this->commandBus->dispatch(new UpdateCompetitionMatchSelectionCommand(
                        editorId: $user->id,
                        competitionId: $competition->id,
                        selectedMatchIds: $selectedMatchIds,
                    ));

                    $this->addFlash('success', 'Výběr zápasů byl uložen.');
                } catch (HandlerFailedException $e) {
                    $previous = $e->getPrevious();

                    // Covers MatchNotInCompetition and the empty-selection guard.
                    if (!$previous instanceof \DomainException) {
                        throw $e;
                    }

                    $this->addFlash('error', $previous->getMessage());
                }
            }

            return $this->redirectToRoute('portal_competition_match_selection', ['id' => $competition->id->toRfc4122()]);
        }

        $selectedIds = array_flip($this->selectionRepository->selectedMatchIds($competition->id));

        $selectable = array_values(array_filter(
            $this->sportMatchRepository->listByMatchSource($competition->matchSource->id),
            static fn (SportMatch $match): bool => !$match->isCancelled,
        ));

        $groups = [];

        foreach ($selectable as $match) {
            $group = $match->round ?? $match->kickoffAt->setTimezone(new \DateTimeZone('Europe/Prague'))->format('j. n. Y');
            $groups[$group][] = [
                'match' => $match,
                'checked' => isset($selectedIds[$match->id->toRfc4122()]),
            ];
        }

        return $this->render('portal/competition/match_selection.html.twig', [
            'competition' => $competition,
            'groups' => $groups,
        ]);
    }
}
