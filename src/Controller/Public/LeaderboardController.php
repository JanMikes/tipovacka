<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\Competition;
use App\Entity\User;
use App\Enum\CompetitionBrowseScope;
use App\Enum\LeaderboardSort;
use App\Enum\LeaderboardTimeFilter;
use App\Query\GetCompetitionCurrentRound\GetCompetitionCurrentRound;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\GetCompetitionMatchProgress\GetCompetitionMatchProgress;
use App\Query\ListBrowsableCompetitions\BrowsableCompetitionItem;
use App\Query\ListBrowsableCompetitions\ListBrowsableCompetitions;
use App\Query\ListMyCompetitions\CompetitionListItem;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Service\Leaderboard\LeaderboardTableBuilder;
use App\Value\CompetitionSwitcherOption;
use App\Value\LeaderboardStanding;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * „Žebříček" — the standalone, publicly viewable leaderboard page (item 05).
 *
 * **No `#[IsGranted('ROLE_USER')]` on purpose.** Since item 09 the audience of a
 * page is declared on its controller, and this one is public: a logged-out visitor
 * sees the board of a public global competition. Everything that is not public is
 * still refused by {@see LeaderboardVoter} — a private competition's board is not
 * reachable by guessing its UUID, signed in or not.
 *
 * The competition is chosen with `?soutez={uuid}`, never a path segment: the
 * `<twig:SoutezSwitcher>` is a plain GET form and can only append a query string.
 * The three deeper sub-pages (matice / clen / shoda) hang off `/zebricek/…` and
 * carry the same parameter — they stay members-only.
 *
 * All of the page state lives in the URL (`soutez`, `obdobi`, `hledat`, `razeni`,
 * `vse`), so every view of this page is linkable and works without JavaScript.
 */
#[Route('/zebricek', name: 'leaderboard', methods: ['GET'])]
final class LeaderboardController extends AbstractController
{
    /** Enough options for the picker without paging it; the scope is admin-curated and small. */
    private const int PUBLIC_SWITCHER_LIMIT = 50;

    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly QueryBus $queryBus,
        private readonly LeaderboardTableBuilder $tableBuilder,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        $user = $user instanceof User ? $user : null;

        /** @var list<CompetitionListItem> $myCompetitions */
        $myCompetitions = null !== $user
            ? $this->queryBus->handle(new ListMyCompetitions(userId: $user->id))
            : [];

        // The switcher feed: the viewer's own soutěže, or — logged out, or logged in
        // with none yet — the public global competitions anybody may look at.
        $switcherOptions = [] !== $myCompetitions
            ? array_map(self::optionFromMine(...), $myCompetitions)
            : $this->publicSwitcherOptions($user);

        $competition = $this->resolveCompetition($request->query->getString('soutez'), $switcherOptions);

        if (null === $competition) {
            return $this->render('public/leaderboard.html.twig', [
                'competition' => null,
                'switcher_competitions' => [],
            ]);
        }

        // A viewer can land on a public global competition that is not in their own
        // list (a shared link, the /souteze grid). Offer it in the picker too, so the
        // control never claims to show a different soutěž than the page does.
        $switcherOptions = self::withSelected($switcherOptions, $competition);

        $filter = LeaderboardTimeFilter::fromRequest($request->query->getString('obdobi'));
        $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard(
            competitionId: $competition->id,
            filter: $filter,
        ));

        // Everything on this page reads from ONE board resolved for the active
        // filter — the table, the podium and the „Tvoje pozice" strip — so a
        // windowed tab never shows a strip rank that contradicts the re-ranked
        // table. Under a window the board carries no Δ (snapshots are all-time
        // only), so the strip's „od minula" movement simply does not render there.
        $standing = LeaderboardStanding::fromRows($leaderboard->rows, $user?->id);
        $meRow = $standing?->row;

        $currentRound = $this->queryBus->handle(new GetCompetitionCurrentRound(competitionId: $competition->id));
        $progress = $this->queryBus->handle(new GetCompetitionMatchProgress(competitionId: $competition->id));

        // Top-3 podium — only meaningful once there are ≥3 players and someone has scored.
        $podiumRows = [];
        if (count($leaderboard->rows) >= 3 && $leaderboard->rows[0]->totalPoints > 0) {
            $podiumRows = array_slice($leaderboard->rows, 0, 3);
        }

        $table = $this->tableBuilder->build(
            rows: $leaderboard->rows,
            search: trim($request->query->getString('hledat')),
            sort: $request->query->getString('razeni'),
            meRow: $meRow,
            expanded: '' !== $request->query->getString('vse'),
        );

        // The winner banner is the competition's overall champion — an all-time
        // fact — so it is only shown on the all-time board, never derived from a
        // windowed (e.g. „Týden") re-ranking.
        $winner = null;
        if ($competition->matchSource->isCompleted && LeaderboardTimeFilter::AllTime === $filter) {
            foreach ($leaderboard->rows as $row) {
                if (1 === $row->rank) {
                    $winner = $row;

                    break;
                }
            }
        }

        return $this->render('public/leaderboard.html.twig', [
            'competition' => $competition,
            'switcher_competitions' => $switcherOptions,
            'winner' => $winner,
            'podium_rows' => $podiumRows,
            'me_row' => $meRow,
            'player_count' => count($leaderboard->rows),
            'gap_to_top3' => $standing?->gapToTop3,
            'gap_to_top5' => $standing?->gapToTop5,
            'current_round' => $currentRound,
            'progress' => $progress,
            'table' => $table,
            'show_delta' => $leaderboard->showDelta,
            'active_filter' => $filter,
            'time_filters' => LeaderboardTimeFilter::cases(),
            'search' => trim($request->query->getString('hledat')),
            'sort' => $table->sort,
            'sort_options' => LeaderboardSort::cases(),
        ]);
    }

    /**
     * The competition the page shows: the requested one when the viewer may see it,
     * otherwise the first sensible option (a running soutěž before a finished one).
     * Falling back rather than 403-ing on a foreign id keeps the nav entry — which
     * carries no id — always landing on something.
     *
     * @param list<CompetitionSwitcherOption> $options
     */
    private function resolveCompetition(string $requestedId, array $options): ?Competition
    {
        if ('' !== $requestedId && Uuid::isValid($requestedId)) {
            $requested = $this->competitionRepository->find(Uuid::fromString($requestedId));

            if (null !== $requested && $this->isGranted(LeaderboardVoter::VIEW, $requested)) {
                return $requested;
            }
        }

        foreach ([false, true] as $finished) {
            foreach ($options as $option) {
                if ($option->isFinished !== $finished) {
                    continue;
                }

                $candidate = $this->competitionRepository->find(Uuid::fromString($option->id));

                if (null !== $candidate && $this->isGranted(LeaderboardVoter::VIEW, $candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * The public feed: global competitions anybody may browse. Same scope as the
     * „Veřejné soutěže" grid on `/souteze`, so the picker never offers a board the
     * discovery page does not list.
     *
     * @return list<CompetitionSwitcherOption>
     */
    private function publicSwitcherOptions(?User $user): array
    {
        $browsable = $this->queryBus->handle(new ListBrowsableCompetitions(
            scope: CompetitionBrowseScope::Discoverable,
            viewerId: $user?->id,
            pageSize: self::PUBLIC_SWITCHER_LIMIT,
        ));

        return array_map(
            static fn (BrowsableCompetitionItem $item): CompetitionSwitcherOption => CompetitionSwitcherOption::fromDates(
                id: $item->competitionId->toRfc4122(),
                name: $item->name,
                subtitle: $item->matchSourceName,
                startAt: $item->sourceStartAt,
                endAt: $item->sourceEndAt,
                isFinished: $item->isFinished,
            ),
            $browsable->items,
        );
    }

    private static function optionFromMine(CompetitionListItem $item): CompetitionSwitcherOption
    {
        return CompetitionSwitcherOption::fromDates(
            id: $item->competitionId->toRfc4122(),
            name: $item->competitionName,
            subtitle: $item->matchSourceName,
            startAt: $item->matchSourceStartAt,
            endAt: $item->matchSourceEndAt,
            isFinished: $item->matchSourceIsCompleted,
        );
    }

    /**
     * @param list<CompetitionSwitcherOption> $options
     *
     * @return list<CompetitionSwitcherOption>
     */
    private static function withSelected(array $options, Competition $competition): array
    {
        $id = $competition->id->toRfc4122();

        foreach ($options as $option) {
            if ($option->id === $id) {
                return $options;
            }
        }

        array_unshift($options, CompetitionSwitcherOption::fromDates(
            id: $id,
            name: $competition->name,
            subtitle: $competition->matchSource->name,
            startAt: $competition->matchSource->startAt,
            endAt: $competition->matchSource->endAt,
            isFinished: $competition->matchSource->isCompleted,
        ));

        return $options;
    }
}
