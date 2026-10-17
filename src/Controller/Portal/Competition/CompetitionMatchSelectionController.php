<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\UpdateCompetitionMatchSelection\UpdateCompetitionMatchSelectionCommand;
use App\Command\UpdateCompetitionTeamFilter\UpdateCompetitionTeamFilterCommand;
use App\Entity\Competition;
use App\Entity\CompetitionSource;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionTeamFilterRepository;
use App\Repository\SportMatchRepository;
use App\Value\TeamView;
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
 * Manage which matches a competition includes. The surface adapts to the LAYER
 * being managed: Subset ⇒ a per-match checklist; Teams ⇒ a team-filter picker;
 * All ⇒ nothing to manage. A soutěž drawing from several zdroje manages one
 * layer at a time, chosen with `?vrstva={id}`; the default is its first
 * manageable one, so a single-zdroj competition never sees the choice.
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

        $layer = $this->resolveLayer($competition, $request->query->get('vrstva'));

        if (null === $layer) {
            return $this->redirectAllModeToDetail($competition);
        }

        return match ($layer->selectionMode) {
            CompetitionMatchSelectionMode::Subset => $this->manageSubset($request, $competition, $layer, $user),
            CompetitionMatchSelectionMode::Teams => $this->manageTeams($request, $competition, $layer, $user),
            CompetitionMatchSelectionMode::All => $this->redirectAllModeToDetail($competition),
        };
    }

    /**
     * The layer to manage: the one asked for by `?vrstva`, else the first that
     * has anything to manage at all. Null when no layer does — every layer
     * takes its whole zdroj.
     */
    private function resolveLayer(Competition $competition, mixed $requestedId): ?CompetitionSource
    {
        if (\is_string($requestedId) && Uuid::isValid($requestedId)) {
            foreach ($competition->sources as $layer) {
                if ($layer->id->equals(Uuid::fromString($requestedId))) {
                    return $layer;
                }
            }
        }

        foreach ($competition->sources as $layer) {
            if (CompetitionMatchSelectionMode::All !== $layer->selectionMode) {
                return $layer;
            }
        }

        return null;
    }

    /**
     * The layers a manager can actually edit — what the layer switcher offers.
     * Rendered only when there is more than one.
     *
     * @return list<CompetitionSource>
     */
    private function manageableLayers(Competition $competition): array
    {
        return array_values(array_filter(
            $competition->sources,
            static fn (CompetitionSource $layer): bool => CompetitionMatchSelectionMode::All !== $layer->selectionMode,
        ));
    }

    private function redirectAllModeToDetail(Competition $competition): Response
    {
        $this->addFlash('info', 'Tato soutěž zahrnuje všechny zápasy zdroje — výběr zápasů se nespravuje.');

        return $this->redirectToRoute('competition_settings', ['id' => $competition->id->toRfc4122()]);
    }

    private function manageSubset(Request $request, Competition $competition, CompetitionSource $layer, User $user): Response
    {
        if ($request->isMethod('POST')) {
            $this->assertCsrf($request, $competition);

            $selectedMatchIds = [];

            foreach ($request->request->all('matches') as $matchId) {
                if (\is_string($matchId) && Uuid::isValid($matchId)) {
                    $selectedMatchIds[] = Uuid::fromString($matchId);
                }
            }

            // A refused selection is re-rendered AS SUBMITTED (422) with the reason
            // above the checklist — a redirect would throw the organizer's ticks
            // away and put the message in a flash above the saved state.
            if (0 === count($selectedMatchIds)) {
                return $this->renderSubset($competition, $layer, [], 'Soutěž musí mít v tomto zdroji aspoň jeden zápas. Zaškrtněte zápasy, které se mají tipovat — nebo zdroj ze soutěže odeberte v „Zápasy soutěže".');
            }

            try {
                $this->commandBus->dispatch(new UpdateCompetitionMatchSelectionCommand(
                    editorId: $user->id,
                    competitionId: $competition->id,
                    selectedMatchIds: $selectedMatchIds,
                    competitionSourceId: $layer->id,
                ));
            } catch (HandlerFailedException $e) {
                return $this->renderSubset($competition, $layer, $selectedMatchIds, $this->domainRefusal($e));
            }

            $this->addFlash('success', 'Výběr zápasů byl uložen.');

            return $this->redirectToRoute('competition_match_selection', [
                'id' => $competition->id->toRfc4122(),
                'vrstva' => $layer->id->toRfc4122(),
            ]);
        }

        return $this->renderSubset($competition, $layer, null, null);
    }

    /**
     * @param list<Uuid>|null $submittedIds the ticks of a refused submit, or null for the saved selection
     */
    private function renderSubset(Competition $competition, CompetitionSource $layer, ?array $submittedIds, ?string $error): Response
    {
        $selectedIds = null === $submittedIds
            ? array_flip($this->selectionRepository->selectedMatchIdsForLayer($layer->id))
            : array_flip(array_map(static fn (Uuid $id): string => $id->toRfc4122(), $submittedIds));

        $selectable = array_values(array_filter(
            $this->sportMatchRepository->listByMatchSource($layer->matchSource->id),
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
            'layer' => $layer,
            'layers' => $this->manageableLayers($competition),
            'mode' => 'subset',
            'groups' => $groups,
            'error' => $error,
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    private function manageTeams(Request $request, Competition $competition, CompetitionSource $layer, User $user): Response
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
                return $this->renderTeams($competition, $layer, [], 'Vyberte prosím alespoň jeden tým — začněte psát jeho název do pole a vyberte ho z nabídky. Soutěž bez týmu by neměla žádné zápasy.');
            }

            try {
                $this->commandBus->dispatch(new UpdateCompetitionTeamFilterCommand(
                    editorId: $user->id,
                    competitionId: $competition->id,
                    teamIds: $teamIds,
                    competitionSourceId: $layer->id,
                ));
            } catch (HandlerFailedException $e) {
                return $this->renderTeams($competition, $layer, null, $this->domainRefusal($e));
            }

            $this->addFlash('success', 'Filtr týmů byl uložen.');

            return $this->redirectToRoute('competition_match_selection', [
                'id' => $competition->id->toRfc4122(),
                'vrstva' => $layer->id->toRfc4122(),
            ]);
        }

        return $this->renderTeams($competition, $layer, null, null);
    }

    /**
     * @param list<TeamView>|null $submittedTeams the picks of a refused submit, or null for the saved filter
     */
    private function renderTeams(Competition $competition, CompetitionSource $layer, ?array $submittedTeams, ?string $error): Response
    {
        return $this->render('portal/competition/match_selection.html.twig', [
            'competition' => $competition,
            'layer' => $layer,
            'layers' => $this->manageableLayers($competition),
            'mode' => 'teams',
            'filter_teams' => $submittedTeams ?? $this->teamFilterRepository->teamViewsForLayer($layer->id),
            'error' => $error,
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    private function assertCsrf(Request $request, Competition $competition): void
    {
        $csrfToken = $request->request->get('_token');

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid('competition_match_selection_'.$competition->id->toRfc4122(), $csrfToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * The domain's own words for a refused selection (MatchNotInCompetition /
     * TeamNotInSource / the empty-selection guard) — written for the organizer.
     */
    private function domainRefusal(HandlerFailedException $e): string
    {
        $previous = $e->getPrevious();

        if (!$previous instanceof \DomainException) {
            throw $e;
        }

        return $previous->getMessage();
    }
}
