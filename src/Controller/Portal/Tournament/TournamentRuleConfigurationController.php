<?php

declare(strict_types=1);

namespace App\Controller\Portal\Tournament;

use App\Command\UpdateTournamentRuleConfiguration\UpdateTournamentRuleConfigurationCommand;
use App\Entity\User;
use App\Form\TournamentRuleConfigurationFormData;
use App\Form\TournamentRuleConfigurationFormType;
use App\Query\GetTournamentRuleConfiguration\GetTournamentRuleConfiguration;
use App\Query\QueryBus;
use App\Repository\TournamentRepository;
use App\Voter\TournamentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/portal/turnaje/{id}/pravidla', name: 'portal_tournament_rule_configuration', requirements: ['id' => Requirement::UUID])]
final class TournamentRuleConfigurationController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly QueryBus $queryBus,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $tournament = $this->tournamentRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(TournamentVoter::EDIT, $tournament);

        /** @var User $user */
        $user = $this->getUser();

        $result = $this->queryBus->handle(new GetTournamentRuleConfiguration(
            tournamentId: $tournament->id,
        ));

        $formData = TournamentRuleConfigurationFormData::fromResult($result);
        $form = $this->createForm(TournamentRuleConfigurationFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $changes = [];
            foreach ($formData->rules as $identifier => $entry) {
                $changes[$identifier] = [
                    'enabled' => $entry->enabled,
                    'points' => $entry->points,
                ];
            }

            $this->commandBus->dispatch(new UpdateTournamentRuleConfigurationCommand(
                tournamentId: $tournament->id,
                editorId: $user->id,
                changes: $changes,
            ));

            $this->addFlash('success', 'Pravidla turnaje byla uložena.');

            return $this->redirectToRoute('portal_tournament_rule_configuration', [
                'id' => $tournament->id->toRfc4122(),
            ]);
        }

        return $this->render('portal/tournament/rule_configuration.html.twig', [
            'form' => $form,
            'tournament' => $tournament,
            'items' => $result->items,
            'evaluationCount' => $result->evaluationCount,
        ]);
    }
}
