<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\SoftDeleteSportMatch\SoftDeleteSportMatchCommand;
use App\Entity\User;
use App\Repository\SportMatchRepository;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/zapasy/{id}/smazat',
    name: 'portal_sport_match_delete',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class SoftDeleteController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $tournamentId = $sportMatch->tournament->id->toRfc4122();
        $this->denyAccessUnlessGranted(SportMatchVoter::DELETE, $sportMatch);

        if (!$this->isCsrfTokenValid('sport_match_delete_'.$sportMatch->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new SoftDeleteSportMatchCommand(
            sportMatchId: $sportMatch->id,
            editorId: $user->id,
        ));

        $this->addFlash('success', 'Zápas byl smazán.');

        return $this->redirectToRoute('portal_tournament_detail', ['id' => $tournamentId]);
    }
}
