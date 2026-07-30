<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Entity\User;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\ListMyCompetitions\CompetitionListItem;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Query\ListRecentEvaluatedGuessesForUser\EvaluatedGuessItem;
use App\Query\ListRecentEvaluatedGuessesForUser\ListRecentEvaluatedGuessesForUser;
use App\Query\ListUserMatches\ListUserMatches;
use App\Query\ListUserMatches\UserMatchItem;
use App\Query\QueryBus;
use App\Value\LeaderboardStanding;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * „Nástěnka hráče" — the player's home (item 06).
 *
 * ONE soutěž is in focus at a time and `<twig:SoutezSwitcher>` picks it
 * (`?soutez={uuid}`); the hero standing, the match lists and the žebříček sidebar
 * all follow from it. „Moje soutěže" is the deliberate exception — it stays the
 * full cross-soutěž overview, not a filtered echo of the switcher.
 *
 * The scope is ALWAYS that one soutěž: item 18 deleted the „SOUTĚŽ" roletka
 * (`?zapasy=vse`), which was the only control that widened the two match lists,
 * so the page keeps the promise its hero makes. The cross-soutěž feed is /zapasy.
 *
 * An unknown or foreign `?soutez=` **falls back** to the viewer's default soutěž
 * instead of 403-ing. That is leak prevention, not laziness: guessing a UUID must
 * never reveal that it exists, let alone anything inside it.
 */
#[Route('/nastenka', name: 'dashboard', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    private const string FILTER_ALL = 'vse';
    private const string FILTER_LIVE = 'live';
    private const string FILTER_TODAY = 'dnes';
    private const string FILTER_TIPPABLE = 'tipovatelne';
    private const string FILTER_FINISHED = 'ukoncene';

    private const string TIMEZONE = 'Europe/Prague';

    /** Rows in the žebříček sidebar before the viewer's own row is appended. */
    private const int MINI_LEADERBOARD_ROWS = 5;

    /** „Poslední Tvoje tipy" / „Odehrané zápasy" are teasers — the full lists live elsewhere. */
    private const int RECENT_ROWS = 5;

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // `withMissingTipCounts` because the „Moje soutěže" grid draws the red
        // „Chybí N tipů" badge — resolved once for the whole list, never per card.
        // The other consumers of this query do not ask for it.
        /** @var list<CompetitionListItem> $myCompetitions */
        $myCompetitions = $this->queryBus->handle(new ListMyCompetitions(
            userId: $user->id,
            withMissingTipCounts: true,
        ));

        if ([] === $myCompetitions) {
            return $this->render('portal/dashboard.html.twig', [
                'my_competitions' => [],
                'selected_competition' => null,
            ]);
        }

        $selectedCompetition = self::resolveSelected($myCompetitions, $request->query->getString('soutez'));

        // Always ONE soutěž (item 18 deleted the „SOUTĚŽ" widener): the page promises
        // quick actions for the soutěž in focus, and the cross-soutěž feed is /zapasy.
        /** @var list<UserMatchItem> $matches */
        $matches = $this->queryBus->handle(new ListUserMatches(
            userId: $user->id,
            competitionId: $selectedCompetition->competitionId,
        ));

        $counts = $this->countsByFilter($matches);
        $activeFilter = $request->query->getString('filtr');

        if (!array_key_exists($activeFilter, $counts)) {
            $activeFilter = self::FILTER_ALL;
        }

        $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard(
            competitionId: $selectedCompetition->competitionId,
        ));
        $standing = LeaderboardStanding::fromRows($leaderboard->rows, $user->id);

        // The viewer's own row is appended under the top N when they are below it,
        // so the sidebar always answers „where am I" without a second page load.
        $miniRows = array_slice($leaderboard->rows, 0, self::MINI_LEADERBOARD_ROWS);
        $miniMeRow = null !== $standing && $standing->row->rank > self::MINI_LEADERBOARD_ROWS
            ? $standing->row
            : null;

        /** @var list<EvaluatedGuessItem> $evaluatedGuesses */
        $evaluatedGuesses = $this->queryBus->handle(new ListRecentEvaluatedGuessesForUser(userId: $user->id));
        $evaluatedGuesses = self::scopeGuesses($evaluatedGuesses, $selectedCompetition);

        $playedMatches = array_values(array_filter($matches, static fn (UserMatchItem $m): bool => $m->isFinished));

        return $this->render('portal/dashboard.html.twig', [
            'my_competitions' => $myCompetitions,
            'selected_competition' => $selectedCompetition,
            'standing' => $standing,
            'player_count' => count($leaderboard->rows),
            'mini_leaderboard_rows' => $miniRows,
            'mini_me_row' => $miniMeRow,
            'recent_guesses' => array_slice($evaluatedGuesses, 0, self::RECENT_ROWS),
            'matches' => $this->applyFilter($matches, $activeFilter),
            'played_matches' => array_slice($playedMatches, 0, self::RECENT_ROWS),
            'my_tips_by_match' => self::indexGuessesByMatch($evaluatedGuesses),
            'counts' => $counts,
            'filters' => [
                self::FILTER_ALL => 'Vše',
                self::FILTER_LIVE => 'Live',
                self::FILTER_TODAY => 'Dnes',
                self::FILTER_TIPPABLE => 'Tipovatelné',
                self::FILTER_FINISHED => 'Ukončené',
            ],
            'active_filter' => $activeFilter,
        ]);
    }

    /**
     * The soutěž in focus: the requested one when the viewer is in it, otherwise
     * their most recently joined **running** soutěž (`ListMyCompetitions` is ordered
     * most-recently-joined first) — a finished one only when nothing is running.
     * `/zebricek` resolves its default the same way, so both pages agree on „your"
     * soutěž, and a foreign or unknown id lands here rather than 403-ing.
     *
     * @param non-empty-list<CompetitionListItem> $myCompetitions
     */
    private static function resolveSelected(array $myCompetitions, string $requestedId): CompetitionListItem
    {
        foreach ($myCompetitions as $competition) {
            if ($competition->competitionId->toRfc4122() === $requestedId) {
                return $competition;
            }
        }

        foreach ($myCompetitions as $competition) {
            if (!$competition->matchSourceIsCompleted) {
                return $competition;
            }
        }

        return $myCompetitions[0];
    }

    /**
     * @param list<UserMatchItem> $matches
     *
     * @return array<string, int>
     */
    private function countsByFilter(array $matches): array
    {
        $counts = [];

        foreach ([self::FILTER_ALL, self::FILTER_LIVE, self::FILTER_TODAY, self::FILTER_TIPPABLE, self::FILTER_FINISHED] as $filter) {
            $counts[$filter] = count($this->applyFilter($matches, $filter));
        }

        return $counts;
    }

    /**
     * @param list<UserMatchItem> $matches
     *
     * @return list<UserMatchItem>
     */
    private function applyFilter(array $matches, string $filter): array
    {
        $today = \DateTimeImmutable::createFromInterface($this->clock->now())
            ->setTimezone(new \DateTimeZone(self::TIMEZONE))
            ->format('Y-m-d');

        $keep = match ($filter) {
            self::FILTER_LIVE => static fn (UserMatchItem $m): bool => $m->isLive,
            self::FILTER_TODAY => static fn (UserMatchItem $m): bool => $m->kickoffAt
                ->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y-m-d') === $today,
            self::FILTER_TIPPABLE => static fn (UserMatchItem $m): bool => $m->isTippable,
            self::FILTER_FINISHED => static fn (UserMatchItem $m): bool => $m->isFinished,
            default => static fn (UserMatchItem $m): bool => true,
        };

        return array_values(array_filter($matches, $keep));
    }

    /**
     * @param list<EvaluatedGuessItem> $guesses
     *
     * @return list<EvaluatedGuessItem>
     */
    private static function scopeGuesses(array $guesses, CompetitionListItem $selected): array
    {
        return array_values(array_filter(
            $guesses,
            static fn (EvaluatedGuessItem $g): bool => $g->competitionId->equals($selected->competitionId),
        ));
    }

    /**
     * „Odehrané zápasy" renders match rows, but the viewer's own tip and the points
     * it earned live on the evaluated-guess read model — index it so the rows can
     * pick their tip up without a per-row query.
     *
     * @param list<EvaluatedGuessItem> $guesses
     *
     * @return array<string, EvaluatedGuessItem>
     */
    private static function indexGuessesByMatch(array $guesses): array
    {
        $byMatch = [];

        foreach ($guesses as $guess) {
            $byMatch[$guess->sportMatchId->toRfc4122()] ??= $guess;
        }

        return $byMatch;
    }
}
