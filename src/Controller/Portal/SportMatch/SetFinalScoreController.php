<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\UpdateLiveScore\UpdateLiveScoreCommand;
use App\Entity\MatchEvent;
use App\Entity\User;
use App\Form\MatchEventFormData;
use App\Form\PeriodScoreFormData;
use App\Form\SetFinalScoreFormData;
use App\Form\SetFinalScoreFormType;
use App\Repository\MatchEventRepository;
use App\Repository\SportMatchRepository;
use App\Value\MatchEventInput;
use App\Value\PeriodScores;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/zapasy/{id}/skore',
    name: 'portal_sport_match_set_score',
    requirements: ['id' => Requirement::UUID],
)]
final class SetFinalScoreController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MatchEventRepository $matchEventRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::SET_SCORE, $sportMatch);

        $sport = $sportMatch->matchSource->sport;

        $formData = new SetFinalScoreFormData();
        $formData->state = $sportMatch->isLive ? SetFinalScoreFormData::STATE_LIVE : SetFinalScoreFormData::STATE_FINISHED;
        $formData->homeScore = $sportMatch->homeScore;
        $formData->awayScore = $sportMatch->awayScore;
        $formData->overtimeHomeScore = $sportMatch->overtimeHomeScore;
        $formData->overtimeAwayScore = $sportMatch->overtimeAwayScore;
        $formData->periods = $this->prefillPeriods($sportMatch->periodScores?->toArray(), $sport->periodCount);
        $formData->events = array_map(
            static function (MatchEvent $event): MatchEventFormData {
                $row = new MatchEventFormData();
                $row->type = $event->type;
                $row->side = $event->side;
                $row->minute = $event->minute;
                $row->playerName = $event->player->name;

                return $row;
            },
            array_reverse($this->matchEventRepository->listByMatch($sportMatch->id)),
        );

        $allowLive = $sportMatch->isScheduled || $sportMatch->isLive;

        $form = $this->createForm(SetFinalScoreFormType::class, $formData, [
            'home_team' => $sportMatch->homeTeam,
            'away_team' => $sportMatch->awayTeam,
            'allow_live' => $allowLive,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert(null !== $formData->homeScore);
            \assert(null !== $formData->awayScore);

            $periodScores = PeriodScores::fromNullableArray($formData->filledPeriodPairs());
            $events = array_map(
                static function (MatchEventFormData $row): MatchEventInput {
                    \assert(null !== $row->type && null !== $row->side);

                    return new MatchEventInput(
                        type: $row->type,
                        side: $row->side,
                        minute: $row->minute,
                        playerName: $row->playerName,
                    );
                },
                $formData->events,
            );

            // Defense in depth: the form already constrains what can be submitted,
            // but the domain layer has the final word (state transitions, score
            // invariants). Surface its rejections as form errors, not error pages.
            try {
                if ($formData->isFinishing) {
                    $this->commandBus->dispatch(new SetSportMatchFinalScoreCommand(
                        sportMatchId: $sportMatch->id,
                        editorId: $user->id,
                        homeScore: $formData->homeScore,
                        awayScore: $formData->awayScore,
                        periodScores: $periodScores,
                        overtimeHomeScore: $formData->overtimeHomeScore,
                        overtimeAwayScore: $formData->overtimeAwayScore,
                        events: $events,
                        isLastMatch: $formData->isLastMatch,
                    ));

                    $this->addFlash('success', 'Výsledek zápasu byl uložen.');
                } else {
                    $this->commandBus->dispatch(new UpdateLiveScoreCommand(
                        sportMatchId: $sportMatch->id,
                        editorId: $user->id,
                        homeScore: $formData->homeScore,
                        awayScore: $formData->awayScore,
                        periodScores: $periodScores,
                        events: $events,
                    ));

                    $this->addFlash('success', 'Průběžné skóre bylo uloženo.');
                }

                return $this->redirectToRoute('portal_sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
            } catch (HandlerFailedException $handlerFailed) {
                $domainException = null;

                foreach ($handlerFailed->getWrappedExceptions() as $wrapped) {
                    if ($wrapped instanceof \DomainException) {
                        $domainException = $wrapped;

                        break;
                    }
                }

                if (null === $domainException) {
                    throw $handlerFailed;
                }

                $form->addError(new FormError($domainException->getMessage()));
            }
        }

        return $this->render('portal/sport_match/set_score.html.twig', [
            'form' => $form,
            'sport_match' => $sportMatch,
            'sport' => $sport,
            'allow_live' => $allowLive,
        ]);
    }

    /**
     * @param list<array{int, int}>|null $existing
     *
     * @return list<PeriodScoreFormData>
     */
    private function prefillPeriods(?array $existing, int $periodCount): array
    {
        $periods = [];

        for ($index = 0; $index < $periodCount; ++$index) {
            $period = new PeriodScoreFormData();
            $period->homeScore = $existing[$index][0] ?? null;
            $period->awayScore = $existing[$index][1] ?? null;
            $periods[] = $period;
        }

        return $periods;
    }
}
