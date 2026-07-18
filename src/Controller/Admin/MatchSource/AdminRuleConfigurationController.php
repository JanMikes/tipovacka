<?php

declare(strict_types=1);

namespace App\Controller\Admin\MatchSource;

use App\Command\UpdateMatchSourceRuleConfiguration\UpdateMatchSourceRuleConfigurationCommand;
use App\Entity\User;
use App\Form\MatchSourceRuleConfigurationFormData;
use App\Form\MatchSourceRuleConfigurationFormType;
use App\Query\GetMatchSourceRuleConfiguration\GetMatchSourceRuleConfiguration;
use App\Query\QueryBus;
use App\Repository\MatchSourceRepository;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/turnaje/{id}/pravidla', name: 'admin_match_source_rule_configuration', requirements: ['id' => Requirement::UUID])]
final class AdminRuleConfigurationController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly QueryBus $queryBus,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(MatchSourceVoter::EDIT, $matchSource);

        /** @var User $user */
        $user = $this->getUser();

        $result = $this->queryBus->handle(new GetMatchSourceRuleConfiguration(
            matchSourceId: $matchSource->id,
        ));

        $formData = MatchSourceRuleConfigurationFormData::fromResult($result);
        $form = $this->createForm(MatchSourceRuleConfigurationFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $changes = [];
            foreach ($formData->rules as $identifier => $entry) {
                $changes[$identifier] = [
                    'enabled' => $entry->enabled,
                    'points' => $entry->points,
                ];
            }

            $this->commandBus->dispatch(new UpdateMatchSourceRuleConfigurationCommand(
                matchSourceId: $matchSource->id,
                editorId: $user->id,
                changes: $changes,
            ));

            $this->addFlash('success', 'Pravidla zdroje zápasů byla uložena.');

            return $this->redirectToRoute('admin_match_source_rule_configuration', [
                'id' => $matchSource->id->toRfc4122(),
            ]);
        }

        return $this->render('admin/match_source/rule_configuration.html.twig', [
            'form' => $form,
            'match_source' => $matchSource,
            'items' => $result->items,
            'evaluationCount' => $result->evaluationCount,
        ]);
    }
}
