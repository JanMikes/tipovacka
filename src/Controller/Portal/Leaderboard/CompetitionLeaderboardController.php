<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Entity\User;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{competitionId}/zebricek',
    name: 'portal_competition_leaderboard',
    requirements: ['competitionId' => Requirement::UUID],
)]
final class CompetitionLeaderboardController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $competitionId): Response
    {
        $competition = $this->competitionRepository->get(Uuid::fromString($competitionId));
        $this->denyAccessUnlessGranted(LeaderboardVoter::VIEW, $competition);

        /** @var User $user */
        $user = $this->getUser();
        $myCompetitions = $this->queryBus->handle(new ListMyCompetitions(userId: $user->id));

        $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard(competitionId: $competition->id));

        $winner = null;
        if ($competition->matchSource->isCompleted) {
            foreach ($leaderboard->rows as $row) {
                if (1 === $row->rank) {
                    $winner = $row;

                    break;
                }
            }
        }

        // Top-3 podium — only meaningful once there are ≥3 players and someone has scored.
        $podiumRows = [];
        if (count($leaderboard->rows) >= 3 && $leaderboard->rows[0]->totalPoints > 0) {
            $podiumRows = array_slice($leaderboard->rows, 0, 3);
        }

        // "Tvoje pozice" strip — the current user's row + point gaps to the top tiers
        // (Δ rank-change is deferred, so it is intentionally omitted).
        $meRow = null;
        foreach ($leaderboard->rows as $row) {
            if ($row->userId->equals($user->id)) {
                $meRow = $row;

                break;
            }
        }

        $gapToTop3 = null;
        $gapToTop5 = null;
        if (null !== $meRow) {
            if ($meRow->rank > 3 && count($leaderboard->rows) >= 3) {
                $gapToTop3 = max(0, $leaderboard->rows[2]->totalPoints - $meRow->totalPoints);
            }
            if ($meRow->rank > 5 && count($leaderboard->rows) >= 5) {
                $gapToTop5 = max(0, $leaderboard->rows[4]->totalPoints - $meRow->totalPoints);
            }
        }

        return $this->render('portal/leaderboard/index.html.twig', [
            'competition' => $competition,
            'winner' => $winner,
            'my_competitions' => $myCompetitions,
            'podium_rows' => $podiumRows,
            'me_row' => $meRow,
            'player_count' => count($leaderboard->rows),
            'gap_to_top3' => $gapToTop3,
            'gap_to_top5' => $gapToTop5,
        ]);
    }
}
