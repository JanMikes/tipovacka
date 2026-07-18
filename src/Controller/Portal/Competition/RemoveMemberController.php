<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\RemoveMember\RemoveMemberCommand;
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
    '/portal/souteze/{id}/clenove/{userId}/odebrat',
    name: 'portal_competition_remove_member',
    requirements: ['id' => Requirement::UUID, 'userId' => Requirement::UUID],
    methods: ['POST'],
)]
final class RemoveMemberController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id, string $userId): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);

        $csrfId = 'competition_remove_member_'.$competition->id->toRfc4122().'_'.$userId;
        if (!$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new RemoveMemberCommand(
            ownerId: $currentUser->id,
            competitionId: $competition->id,
            targetUserId: Uuid::fromString($userId),
        ));

        $this->addFlash('success', 'Člen byl odebrán ze soutěže.');

        return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
    }
}
