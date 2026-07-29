<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\UpdateCompetitionMatchSelection\UpdateCompetitionMatchSelectionCommand;
use App\Command\UpdateCompetitionTeamFilter\UpdateCompetitionTeamFilterCommand;
use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionTeamFilterRepository;
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

/**
 * Manage which matches a competition includes. The surface adapts to the
 * competition's selection mode: Subset ⇒ a per-match checklist; Teams ⇒ a
 * team-filter picker; All ⇒ nothing to manage (redirect back).
 */
#[Route(
    '/souteze/{id}/zapasy-vyber',
    name: 'competition_match_selection',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET', 'POST'],
)]
#[IsGranted('ROLE_USER')]
final class CompetitionMatchSelectionController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly CompetitionMatchSelectionRepository $selectionRepository,
        private readonly CompetitionTeamFilterRepository $teamFilterRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        return match ($competition->selectionMode) {
            CompetitionMatchSelectionMode::Subset => $this->manageSubset($request, $competition, $user),
            CompetitionMatchSelectionMode::Teams => $this->manageTeams($request, $competition, $user),
            CompetitionMatchSelectionMode::All => $this->redirectAllModeToDetail($competition),
        };
    }

    private function redirectAllModeToDetail(Competition $competition): Response
    {
        $this->addFlash('info', 'Tato soutěž zahrnuje všechny zápasy zdroje — výběr zápasů se nespravuje.');

        return $this->redirectToRoute('competition_detail', ['id' => $competition->id->toRfc4122()]);
    }

    private function manageSubset(Request $request, Competition $competition, User $user): Response
    {
        if ($request->isMethod('POST')) {
            $this->assertCsrf($request, $competition);

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
                    $this->flashDomainError($e);
                }
            }

            return $this->redirectToRoute('competition_match_selection', ['id' => $competition->id->toRfc4122()]);
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
            'mode' => 'subset',
            'groups' => $groups,
        ]);
    }

    private function manageTeams(Request $request, Competition $competition, User $user): Response
    {
        if ($request->isMethod('POST')) {
            $this->assertCsrf($request, $competition);

            $teamIds = [];

            foreach ($request->request->all('teams') as $teamId) {
                if (\is_string($teamId) && Uuid::isValid($teamId)) {
                    $teamIds[] = Uuid::fromString($teamId);
                }
            }

            if (0 === count($teamIds)) {
                $this->addFlash('error', 'Vyberte prosím alespoň jeden tým.');
            } else {
                try {
                    $this->commandBus->dispatch(new UpdateCompetitionTeamFilterCommand(
                        editorId: $user->id,
                        competitionId: $competition->id,
                        teamIds: $teamIds,
                    ));

                    $this->addFlash('success', 'Filtr týmů byl uložen.');
                } catch (HandlerFailedException $e) {
                    $this->flashDomainError($e);
                }
            }

            return $this->redirectToRoute('competition_match_selection', ['id' => $competition->id->toRfc4122()]);
        }

        return $this->render('portal/competition/match_selection.html.twig', [
            'competition' => $competition,
            'mode' => 'teams',
            'filter_teams' => $this->teamFilterRepository->teamViewsFor($competition->id),
        ]);
    }

    private function assertCsrf(Request $request, Competition $competition): void
    {
        $csrfToken = $request->request->get('_token');

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid('competition_match_selection_'.$competition->id->toRfc4122(), $csrfToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function flashDomainError(HandlerFailedException $e): void
    {
        $previous = $e->getPrevious();

        // Covers MatchNotInCompetition / TeamNotInSource and the empty-selection guard.
        if (!$previous instanceof \DomainException) {
            throw $e;
        }

        $this->addFlash('error', $previous->getMessage());
    }
}
