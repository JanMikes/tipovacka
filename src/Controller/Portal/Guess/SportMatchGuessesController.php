<?php

declare(strict_types=1);

namespace App\Controller\Portal\Guess;

use App\Entity\User;
use App\Form\CompetitionMatchDeadlineFormData;
use App\Form\CompetitionMatchDeadlineFormType;
use App\Query\GetMatchPickDistribution\GetMatchPickDistribution;
use App\Query\GetMatchRanking\GetMatchRanking;
use App\Query\QueryBus;
use App\Repository\CompetitionMatchSettingRepository;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Service\EffectiveTipDeadlineResolver;
use App\Voter\CompetitionVoter;
use App\Voter\GuessVoter;
use App\Voter\GuessVotingContext;
use App\Voter\SportMatchVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{competitionId}/zapasy/{sportMatchId}',
    name: 'portal_competition_sport_match_guesses',
    requirements: ['competitionId' => Requirement::UUID, 'sportMatchId' => Requirement::UUID],
)]
final class SportMatchGuessesController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
        private readonly CompetitionMatchSettingRepository $competitionMatchSettingRepository,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly ClockInterface $clock,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $competitionId, string $sportMatchId): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($competitionId));
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchId));

        $this->denyAccessUnlessGranted(CompetitionVoter::VIEW, $competition);
        $this->denyAccessUnlessGranted(SportMatchVoter::VIEW, $sportMatch);

        $context = new GuessVotingContext(sportMatch: $sportMatch, competitionId: $competition->id);
        $this->denyAccessUnlessGranted(GuessVoter::VIEW, $context);

        $isCompetitionManager = $this->isGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);
        $effectiveDeadline = $this->deadlineResolver->resolve($competition, $sportMatch);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $canSeeAllTips = $isCompetitionManager
            || !$competition->hideOthersTipsBeforeDeadline
            || $now >= $effectiveDeadline;

        // Pick distribution (1/X/2) is only shown once others' tips are visible
        // (after the deadline, or when the competition doesn't hide them).
        $pickDistribution = $canSeeAllTips
            ? $this->queryBus->handle(new GetMatchPickDistribution(
                competitionId: $competition->id,
                sportMatchId: $sportMatch->id,
            ))
            : null;

        // Per-match ranking ("Pořadí za zápas") needs evaluated guesses, so it only
        // makes sense once the match is finished and others' tips are visible.
        $matchRanking = ($canSeeAllTips && $sportMatch->isFinished)
            ? $this->queryBus->handle(new GetMatchRanking(
                competitionId: $competition->id,
                sportMatchId: $sportMatch->id,
            ))
            : null;

        $memberRows = [];

        if ($isCompetitionManager) {
            $memberships = $this->membershipRepository->findActiveByCompetition($competition->id);
            foreach ($memberships as $membership) {
                $guess = $this->guessRepository->findActiveByUserMatchCompetition(
                    $membership->user->id,
                    $sportMatch->id,
                    $competition->id,
                );
                $memberRows[] = [
                    'user' => $membership->user,
                    'guess' => $guess,
                ];
            }
        }

        $deadlineForm = null;

        if ($this->isGranted(CompetitionVoter::EDIT, $competition)) {
            $existingSetting = $this->competitionMatchSettingRepository->findByCompetitionAndMatch(
                $competition->id,
                $sportMatch->id,
            );
            $deadlineForm = $this->createForm(
                CompetitionMatchDeadlineFormType::class,
                CompetitionMatchDeadlineFormData::fromSetting($existingSetting),
                [
                    'action' => $this->generateUrl('portal_competition_sport_match_set_deadline', [
                        'competitionId' => $competition->id->toRfc4122(),
                        'sportMatchId' => $sportMatch->id->toRfc4122(),
                    ]),
                ],
            )->createView();
        }

        return $this->render('portal/guess/detail.html.twig', [
            'competition' => $competition,
            'sport_match' => $sportMatch,
            'member_rows' => $memberRows,
            'effective_deadline' => $effectiveDeadline,
            'can_see_all_tips' => $canSeeAllTips,
            'pick_distribution' => $pickDistribution,
            'match_ranking' => $matchRanking,
            'deadline_form' => $deadlineForm,
            'current_user_id' => $currentUser->id,
        ]);
    }
}
