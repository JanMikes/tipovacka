<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Entity\User;
use App\Query\GetMemberGroupStats\GetMemberGroupStats;
use App\Query\ListDiscoverablePublicTournaments\ListDiscoverablePublicTournaments;
use App\Query\ListMyGroups\ListMyGroups;
use App\Query\ListMyOwnedTournaments\ListMyOwnedTournaments;
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

        $myGroups = $this->queryBus->handle(new ListMyGroups(userId: $user->id));
        $myOwnedTournaments = $this->queryBus->handle(new ListMyOwnedTournaments(ownerId: $user->id));
        $upcomingMatches = $this->queryBus->handle(new ListUpcomingMatchesForUser(userId: $user->id));
        $evaluatedGuesses = $this->queryBus->handle(new ListRecentEvaluatedGuessesForUser(userId: $user->id));
        $discoverableTournaments = $this->queryBus->handle(new ListDiscoverablePublicTournaments(userId: $user->id));

        // Personal stat cards are scoped to the selected soutěž. The switcher passes
        // ?soutez=<groupId>; default to the most recently joined group. A foreign or
        // unknown id falls back to that default, so other groups' stats never leak.
        $selectedGroup = null;
        $memberStats = null;

        if (count($myGroups) > 0) {
            $requestedGroupId = $request->query->get('soutez');
            $selectedGroup = $myGroups[0];

            foreach ($myGroups as $group) {
                if ($group->groupId->toRfc4122() === $requestedGroupId) {
                    $selectedGroup = $group;

                    break;
                }
            }

            $memberStats = $this->queryBus->handle(new GetMemberGroupStats(
                userId: $user->id,
                groupId: $selectedGroup->groupId,
            ));
        }

        return $this->render('portal/dashboard.html.twig', [
            'my_groups' => $myGroups,
            'my_owned_tournaments' => $myOwnedTournaments,
            'upcoming_matches' => $upcomingMatches,
            'evaluated_guesses' => $evaluatedGuesses,
            'discoverable_tournaments' => $discoverableTournaments,
            'selected_group' => $selectedGroup,
            'member_stats' => $memberStats,
        ]);
    }
}
