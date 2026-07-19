<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\RegenerateCompetitionPin\RegenerateCompetitionPinCommand;
use App\Entity\User;
use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}/pin/novy',
    name: 'portal_competition_pin_regenerate',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class RegeneratePinController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_JOIN_MECHANICS, $competition);

        if (!$this->isCsrfTokenValid('competition_pin_regenerate_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new RegenerateCompetitionPinCommand(
            ownerId: $user->id,
            competitionId: $competition->id,
        ));

        $this->addFlash('success', 'PIN byl obnoven.');

        return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
    }
}
