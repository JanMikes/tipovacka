<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\UpdateSportMatch\UpdateSportMatchCommand;
use App\Entity\User;
use App\Exception\SportMatchCannotBeEdited;
use App\Exception\SportMatchTeamsLocked;
use App\Form\SportMatchFormData;
use App\Form\SportMatchFormType;
use App\Repository\SportMatchRepository;
use App\Service\Competition\ScopeReturn;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/zapasy/{id}/upravit',
    name: 'sport_match_edit',
    requirements: ['id' => Requirement::UUID],
)]
#[IsGranted('ROLE_USER')]
final class UpdateSportMatchController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly ScopeReturn $scopeReturn,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::EDIT, $sportMatch);

        // „Přišel jsem sem ze soutěže" survives the POST through the form action.
        $scopeCompetitionId = $this->scopeReturn->competitionId($request);

        $formData = SportMatchFormData::fromSportMatch($sportMatch);
        $form = $this->createForm(SportMatchFormType::class, $formData, [
            'teams_url' => $this->generateUrl('match_source_teams', ['id' => $sportMatch->matchSource->id->toRfc4122()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Captured before the command: the handler may already have swapped
            // the teams on the (then rolled-back) entity when it refuses.
            $currentHome = $sportMatch->homeTeam->name;

            try {
                $this->commandBus->dispatch(new UpdateSportMatchCommand(
                    sportMatchId: $sportMatch->id,
                    editorId: $user->id,
                    homeTeam: $formData->homeTeam,
                    awayTeam: $formData->awayTeam,
                    kickoffAt: $formData->kickoffAt,
                    venue: $formData->venue ?: null,
                    round: $formData->round ?: null,
                    isPlayoff: $formData->isPlayoff,
                ));

                $this->addFlash('success', 'Zápas byl uložen.');

                if (null !== $scopeCompetitionId) {
                    return $this->redirectToRoute('competition_scope', ['id' => $scopeCompetitionId]);
                }

                return $this->redirectToRoute('sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
            } catch (HandlerFailedException $e) {
                $refusal = $e->getPrevious();

                // The domain's refusals are written for the organizer — put them on
                // the field they are about (re-rendered with 422) instead of a 409 page.
                if ($refusal instanceof SportMatchTeamsLocked) {
                    $field = 0 !== strcasecmp(trim($formData->homeTeam), $currentHome) ? 'homeTeam' : 'awayTeam';
                    $form->get($field)->addError(new FormError($refusal->getMessage()));
                } elseif ($refusal instanceof SportMatchCannotBeEdited) {
                    $form->addError(new FormError($refusal->getMessage()));
                } else {
                    throw $e;
                }
            }
        }

        return $this->render('portal/sport_match/form.html.twig', [
            'form' => $form,
            'match_source' => $sportMatch->matchSource,
            'sport_match' => $sportMatch,
            'mode' => 'edit',
            'scope_competition_id' => $scopeCompetitionId,
        ]);
    }
}
