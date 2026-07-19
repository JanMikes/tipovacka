<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\RegenerateShareableLink\RegenerateShareableLinkCommand;
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
    '/portal/souteze/{id}/odkaz/novy',
    name: 'portal_competition_link_regenerate',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class RegenerateShareableLinkController extends AbstractController
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

        if (!$this->isCsrfTokenValid('competition_link_regenerate_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new RegenerateShareableLinkCommand(
            ownerId: $user->id,
            competitionId: $competition->id,
        ));

        $this->addFlash('success', 'Pozvánkový odkaz byl obnoven.');

        return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
    }
}
