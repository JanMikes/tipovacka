<?php

declare(strict_types=1);

namespace App\Controller\Admin\Competition;

use App\Command\SoftDeleteCompetition\SoftDeleteCompetitionCommand;
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

#[Route('/admin/souteze/{id}/smazat', name: 'admin_competition_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class AdminDeleteCompetitionController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::DELETE, $competition);

        if (!$this->isCsrfTokenValid('admin_competition_delete_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('admin_competition_list');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $this->commandBus->dispatch(new SoftDeleteCompetitionCommand(
            editorId: $admin->id,
            competitionId: $competition->id,
        ));

        $this->addFlash('success', 'Soutěž byla smazána.');

        return $this->redirectToRoute('admin_competition_list');
    }
}
