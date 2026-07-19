<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Entity\User;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\GetMemberCompetitionStats\GetMemberCompetitionStats;
use App\Query\ListDiscoverableGlobalCompetitions\ListDiscoverableGlobalCompetitions;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Query\ListMyOwnedMatchSources\ListMyOwnedMatchSources;
use App\Query\ListRecentEvaluatedGuessesForUser\ListRecentEvaluatedGuessesForUser;
use App\Query\ListUpcomingMatchesForUser\ListUpcomingMatchesForUser;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/nastenka', name: 'portal_dashboard', methods: ['GET'])]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $myCompetitions = $this->queryBus->handle(new ListMyCompetitions(userId: $user->id));
        $myOwnedMatchSources = $this->queryBus->handle(new ListMyOwnedMatchSources(ownerId: $user->id));
        $upcomingMatches = $this->queryBus->handle(new ListUpcomingMatchesForUser(userId: $user->id));
        $evaluatedGuesses = $this->queryBus->handle(new ListRecentEvaluatedGuessesForUser(userId: $user->id));
        $discoverableCompetitions = $this->queryBus->handle(new ListDiscoverableGlobalCompetitions(viewerId: $user->id));
        $walletBalance = $this->queryBus->handle(new GetCreditWallet($user->id))->balance;

        // Personal stat cards are scoped to the selected soutěž. The switcher passes
        // ?soutez=<competitionId>; default to the most recently joined competition. A foreign or
        // unknown id falls back to that default, so other competitions' stats never leak.
        $selectedCompetition = null;
        $memberStats = null;
        $miniLeaderboardRows = [];
        $miniMeRow = null;

        if (count($myCompetitions) > 0) {
            $requestedCompetitionId = $request->query->get('soutez');
            $selectedCompetition = $myCompetitions[0];

            foreach ($myCompetitions as $competition) {
                if ($competition->competitionId->toRfc4122() === $requestedCompetitionId) {
                    $selectedCompetition = $competition;

                    break;
                }
            }

            $memberStats = $this->queryBus->handle(new GetMemberCompetitionStats(
                userId: $user->id,
                competitionId: $selectedCompetition->competitionId,
            ));

            // Mini-leaderboard for the selected soutěž: top 5 + the user's own row
            // appended below if they're outside the top 5 (so they always see it).
            $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard(competitionId: $selectedCompetition->competitionId));
            $miniLeaderboardRows = array_slice($leaderboard->rows, 0, 5);

            foreach ($leaderboard->rows as $row) {
                if ($row->userId->equals($user->id)) {
                    if ($row->rank > 5) {
                        $miniMeRow = $row;
                    }

                    break;
                }
            }
        }

        return $this->render('portal/dashboard.html.twig', [
            'my_competitions' => $myCompetitions,
            'my_owned_match_sources' => $myOwnedMatchSources,
            'upcoming_matches' => $upcomingMatches,
            'evaluated_guesses' => $evaluatedGuesses,
            'discoverable_competitions' => $discoverableCompetitions,
            'wallet_balance' => $walletBalance,
            'selected_competition' => $selectedCompetition,
            'member_stats' => $memberStats,
            'mini_leaderboard_rows' => $miniLeaderboardRows,
            'mini_me_row' => $miniMeRow,
        ]);
    }
}
