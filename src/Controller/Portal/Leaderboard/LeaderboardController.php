<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Entity\User;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Resolves the nav „Žebříček" item: the leaderboard is soutěž-scoped, so redirect
 * to the user's primary (most recently joined) soutěž leaderboard, or to discovery
 * when they are in no soutěž yet.
 *
 * Also the switch target of `<twig:SoutezSwitcher>` on the leaderboard: the picker is a
 * plain GET form, so it can only append `?soutez=<id>` — it cannot fill the competition
 * id into `competition_leaderboard`'s path. An unknown or foreign id falls back to the
 * primary soutěž, so no other competition's board can be reached by guessing an id.
 */
#[Route('/zebricek', name: 'leaderboard', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class LeaderboardController extends AbstractController
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

        if (0 === count($myCompetitions)) {
            return $this->redirectToRoute('dashboard');
        }

        $requestedCompetitionId = $request->query->get('soutez');
        $target = $myCompetitions[0];

        foreach ($myCompetitions as $competition) {
            if ($competition->competitionId->toRfc4122() === $requestedCompetitionId) {
                $target = $competition;

                break;
            }
        }

        return $this->redirectToRoute('competition_leaderboard', [
            'competitionId' => $target->competitionId->toRfc4122(),
        ]);
    }
}
