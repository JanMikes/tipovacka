<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\UpdateCompetitionRuleConfiguration\UpdateCompetitionRuleConfigurationCommand;
use App\Entity\User;
use App\Form\CompetitionRuleConfigurationFormData;
use App\Form\CompetitionRuleConfigurationFormType;
use App\Query\GetCompetitionRuleConfiguration\GetCompetitionRuleConfiguration;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/portal/souteze/{id}/pravidla', name: 'portal_competition_rules', requirements: ['id' => Requirement::UUID])]
final class CompetitionRuleConfigurationController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly QueryBus $queryBus,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        /** @var User $user */
        $user = $this->getUser();

        $result = $this->queryBus->handle(new GetCompetitionRuleConfiguration(
            competitionId: $competition->id,
        ));

        $formData = CompetitionRuleConfigurationFormData::fromResult($result);
        $form = $this->createForm(CompetitionRuleConfigurationFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $changes = [];
            foreach ($formData->rules as $identifier => $entry) {
                $changes[$identifier] = [
                    'enabled' => $entry->enabled,
                    'points' => $entry->points,
                ];
            }

            $this->commandBus->dispatch(new UpdateCompetitionRuleConfigurationCommand(
                competitionId: $competition->id,
                editorId: $user->id,
                changes: $changes,
            ));

            $this->addFlash('success', 'Pravidla soutěže byla uložena.');

            return $this->redirectToRoute('portal_competition_rules', [
                'id' => $competition->id->toRfc4122(),
            ]);
        }

        return $this->render('portal/competition/rule_configuration.html.twig', [
            'form' => $form,
            'competition' => $competition,
            'items' => $result->items,
            'evaluationCount' => $result->evaluationCount,
        ]);
    }
}
