<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Exception\TournamentNotFound;
use App\Query\ListGroupsForTournament\ListGroupsForTournament;
use App\Query\QueryBus;
use App\Repository\MembershipRepository;
use App\Repository\TournamentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/turnaje/{id}', name: 'public_tournament_detail', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class PublicTournamentDetailController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $id): Response
    {
        try {
            $tournament = $this->tournamentRepository->get(Uuid::fromString($id));
        } catch (TournamentNotFound $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        if (TournamentVisibility::Public !== $tournament->visibility || null !== $tournament->deletedAt) {
            throw new NotFoundHttpException('Tournament not found.');
        }

        $groups = $this->queryBus->handle(new ListGroupsForTournament(tournamentId: $tournament->id));

        $user = $this->getUser();
        $memberGroupIds = [];
        if ($user instanceof User) {
            foreach ($this->membershipRepository->findMyActive($user->id) as $membership) {
                if ($membership->group->tournament->id->equals($tournament->id)) {
                    $memberGroupIds[] = $membership->group->id->toRfc4122();
                }
            }
        }

        return $this->render('public/tournament_detail.html.twig', [
            'tournament' => $tournament,
            'groups' => $groups,
            'member_group_ids' => $memberGroupIds,
        ]);
    }
}
