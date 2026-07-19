<?php

declare(strict_types=1);

namespace App\Controller\Portal\Guess;

use App\Command\SubmitGuessOnBehalf\SubmitGuessOnBehalfCommand;
use App\Command\UpdateGuessOnBehalf\UpdateGuessOnBehalfCommand;
use App\Entity\User;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessFeatureNotEnabled;
use App\Exception\InvalidGuessScore;
use App\Exception\MatchNotInCompetition;
use App\Exception\NotAMember;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Guess\GuessScorerWriter;
use App\Voter\CompetitionVoter;
use App\Voter\GuessOnBehalfContext;
use App\Voter\GuessVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{competitionId}/zapasy/{sportMatchId}/clenove/{memberId}/tip',
    name: 'portal_competition_guess_on_behalf',
    requirements: [
        'competitionId' => Requirement::UUID,
        'sportMatchId' => Requirement::UUID,
        'memberId' => Requirement::UUID,
    ],
    methods: ['POST'],
)]
final class SubmitGuessOnBehalfController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly UserRepository $userRepository,
        private readonly GuessRepository $guessRepository,
        private readonly CompetitionGuessFeatures $guessFeatures,
        private readonly GuessScorerWriter $scorerWriter,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $competitionId, string $sportMatchId, string $memberId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($competitionId));
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);

        $csrfToken = $request->request->get('_token');
        $expectedToken = sprintf('guess_on_behalf_%s_%s_%s', $competitionId, $sportMatchId, $memberId);

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid($expectedToken, $csrfToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchId));
        $member = $this->userRepository->get(Uuid::fromString($memberId));

        $homeScore = (int) $request->request->get('homeScore', '-1');
        $awayScore = (int) $request->request->get('awayScore', '-1');

        $redirect = $request->request->getString(
            'redirect_to',
            $this->generateUrl('portal_competition_sport_match_guesses', [
                'competitionId' => $competition->id->toRfc4122(),
                'sportMatchId' => $sportMatch->id->toRfc4122(),
            ]),
        );

        $existing = $this->guessRepository->findActiveByUserMatchCompetition(
            $member->id,
            $sportMatch->id,
            $competition->id,
        );

        try {
            if (null === $existing) {
                $context = new GuessOnBehalfContext($competition, $sportMatch, $member);
                $this->denyAccessUnlessGranted(GuessVoter::SUBMIT_ON_BEHALF, $context);

                $this->commandBus->dispatch(new SubmitGuessOnBehalfCommand(
                    actingUserId: $user->id,
                    targetUserId: $member->id,
                    competitionId: $competition->id,
                    sportMatchId: $sportMatch->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                ));
            } else {
                $this->denyAccessUnlessGranted(GuessVoter::UPDATE_ON_BEHALF, $existing);

                // This form edits only the main score; periods, overtime and
                // scorers of a full-replace update are passed through untouched
                // (filtered by feature enablement — a legacy part of a since-
                // disabled feature is dropped, not rejected; the OT pair travels
                // only while still valid against the NEW tip, else it is dropped).
                $features = $this->guessFeatures->featuresFor($competition->id);
                $carryOvertime = $features->overtimeTip && $existing->overtimeTipValidFor($homeScore, $awayScore);

                $this->commandBus->dispatch(new UpdateGuessOnBehalfCommand(
                    actingUserId: $user->id,
                    guessId: $existing->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                    periodScores: $features->periodTips ? $existing->periodScores : null,
                    overtimeHomeScore: $carryOvertime ? $existing->overtimeHomeScore : null,
                    overtimeAwayScore: $carryOvertime ? $existing->overtimeAwayScore : null,
                    scorers: $features->scorerTips ? $this->scorerWriter->inputsFor($existing) : [],
                ));
            }

            $this->addFlash('success', sprintf('Tip pro %s uložen.', $member->nickname));
        } catch (HandlerFailedException $e) {
            $inner = $e->getPrevious();

            if ($inner instanceof InvalidGuessScore
                || $inner instanceof GuessFeatureNotEnabled
                || $inner instanceof GuessDeadlinePassed
                || $inner instanceof GuessAlreadyExists
                || $inner instanceof NotAMember
                || $inner instanceof MatchNotInCompetition
            ) {
                $this->addFlash('error', $inner->getMessage());
            } else {
                throw $e;
            }
        }

        return $this->redirect($redirect);
    }
}
