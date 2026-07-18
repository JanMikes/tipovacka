<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\RevokeShareableLink\RevokeShareableLinkCommand;
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
    '/portal/souteze/{id}/odkaz/zrusit',
    name: 'portal_competition_link_revoke',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class RevokeShareableLinkController extends AbstractController
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
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);

        if (!$this->isCsrfTokenValid('competition_link_revoke_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new RevokeShareableLinkCommand(
            ownerId: $user->id,
            competitionId: $competition->id,
        ));

        $this->addFlash('success', 'Pozvánkový odkaz byl zrušen.');

        return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
    }
}
