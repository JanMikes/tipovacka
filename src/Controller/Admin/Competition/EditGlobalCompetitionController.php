<?php

declare(strict_types=1);

namespace App\Controller\Admin\Competition;

use App\Command\UpdateGlobalCompetition\UpdateGlobalCompetitionCommand;
use App\Exception\GlobalCompetitionFeeLocked;
use App\Form\GlobalCompetitionFormData;
use App\Form\GlobalCompetitionFormType;
use App\Query\GetCompetitionMonetizationOverview\GetCompetitionMonetizationOverview;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/admin/souteze/{id}/globalni/upravit',
    name: 'admin_global_competition_edit',
    requirements: ['id' => Requirement::UUID],
)]
final class EditGlobalCompetitionController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly MessageBusInterface $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $competition = $this->competitionRepository->get(Uuid::fromString($id));

        if (!$competition->isGlobal) {
            $this->addFlash('error', 'Tato soutěž není globální.');

            return $this->redirectToRoute('admin_competition_list');
        }

        // Fee-lock: fee/monetization are editable only while the owner is the
        // sole member (a non-owner joined ⇒ terms locked).
        $feeLocked = $this->membershipRepository->countActiveMembers($competition->id) > 1;

        $formData = GlobalCompetitionFormData::fromCompetition($competition);
        $form = $this->createForm(GlobalCompetitionFormType::class, $formData, ['with_source' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->commandBus->dispatch(new UpdateGlobalCompetitionCommand(
                    competitionId: $competition->id,
                    entryFeeCredits: $formData->entryFeeCredits,
                    monetization: $formData->monetization,
                ));
            } catch (HandlerFailedException $handlerFailed) {
                if ($handlerFailed->getPrevious() instanceof GlobalCompetitionFeeLocked) {
                    $this->addFlash('error', 'Vstupné už nelze změnit — do soutěže se připojil první hráč.');

                    return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
                }

                throw $handlerFailed;
            }

            $this->addFlash('success', 'Nastavení globální soutěže bylo uloženo.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        return $this->render('admin/competition/edit_global.html.twig', [
            'form' => $form,
            'competition' => $competition,
            'fee_locked' => $feeLocked,
            'monetization' => $this->queryBus->handle(new GetCompetitionMonetizationOverview(
                competitionId: $competition->id,
            )),
        ]);
    }
}
