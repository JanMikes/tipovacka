<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\UpdatePremiumSettings\UpdatePremiumSettingsCommand;
use App\Entity\User;
use App\Enum\CompetitionMonetization;
use App\Form\PremiumSettingsFormData;
use App\Form\PremiumSettingsFormType;
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
    '/portal/souteze/{id}/premium',
    name: 'portal_competition_premium',
    requirements: ['id' => Requirement::UUID],
)]
final class PremiumSettingsController extends AbstractController
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
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        if (CompetitionMonetization::Premium !== $competition->monetization) {
            $this->addFlash('info', 'Prémiové nastavení je dostupné jen u prémiových soutěží.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        $formData = PremiumSettingsFormData::fromCompetition($competition);
        $form = $this->createForm(PremiumSettingsFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new UpdatePremiumSettingsCommand(
                editorId: $user->id,
                competitionId: $competition->id,
                showDistribution: $formData->showDistribution,
                showOthersTips: $formData->showOthersTips,
                allowTipChanges: $formData->allowTipChanges,
                tipChangeOffsetMinutes: $formData->tipChangeOffsetMinutes,
            ));

            $this->addFlash('success', 'Prémiové nastavení bylo uloženo.');

            return $this->redirectToRoute('portal_competition_premium', ['id' => $competition->id->toRfc4122()]);
        }

        return $this->render('portal/competition/premium_settings.html.twig', [
            'form' => $form,
            'competition' => $competition,
        ]);
    }
}
