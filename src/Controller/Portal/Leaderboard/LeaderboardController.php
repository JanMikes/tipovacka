<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Entity\User;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Resolves the nav „Žebříček" item: the leaderboard is soutěž-scoped, so redirect
 * to the user's primary (most recently joined) soutěž leaderboard, or to discovery
 * when they are in no soutěž yet.
 */
#[Route('/zebricek', name: 'leaderboard', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class LeaderboardController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $myCompetitions = $this->queryBus->handle(new ListMyCompetitions(userId: $user->id));

        if (0 === count($myCompetitions)) {
            return $this->redirectToRoute('dashboard');
        }

        return $this->redirectToRoute('competition_leaderboard', [
            'competitionId' => $myCompetitions[0]->competitionId->toRfc4122(),
        ]);
    }
}
