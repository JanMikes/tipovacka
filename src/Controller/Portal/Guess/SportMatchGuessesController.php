<?php

declare(strict_types=1);

namespace App\Controller\Portal\Guess;

use App\Entity\User;
use App\Exception\MatchNotInCompetition;
use App\Form\CompetitionMatchDeadlineFormData;
use App\Form\CompetitionMatchDeadlineFormType;
use App\Query\GetMatchPickDistribution\GetMatchPickDistribution;
use App\Query\GetMatchRanking\GetMatchRanking;
use App\Query\QueryBus;
use App\Repository\CompetitionMatchSettingRepository;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MatchEventRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipVisibilityGate;
use App\Service\EffectiveTipDeadlineResolver;
use App\Voter\CompetitionVoter;
use App\Voter\GuessVoter;
use App\Voter\GuessVotingContext;
use App\Voter\SportMatchVoter;
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
        private readonly MatchEventRepository $matchEventRepository,
        private readonly CompetitionMatchSettingRepository $competitionMatchSettingRepository,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly TipVisibilityGate $visibilityGate,
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

        if (!$this->matchProvider->includes($competition, $sportMatch)) {
            throw MatchNotInCompetition::create();
        }

        $context = new GuessVotingContext(sportMatch: $sportMatch, competitionId: $competition->id);
        $this->denyAccessUnlessGranted(GuessVoter::VIEW, $context);

        $isCompetitionManager = $this->isGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);
        // Per-viewer deadline for THIS user's tip-entry surfaces / displayed „Uzávěrka".
        $effectiveDeadline = $this->deadlineResolver->deadlineFor($competition, $sportMatch, $currentUser);

        // Visibility gate composes THIS viewer's entitlement (premium toggle / own
        // boost — per viewer) with the userless deadline having passed (public to
        // everyone). Distribution and concrete tips are gated independently: an
        // OthersTips buyer sees both, a TipDistribution buyer only the bar.
        $canSeeDistribution = $this->visibilityGate->canSeeDistribution($competition, $currentUser, $sportMatch);
        $canSeeOthersTips = $this->visibilityGate->canSeeOthersTips($competition, $currentUser, $sportMatch);

        // The bar is a cheap aggregate: compute it always so the paywall can show
        // „Uvidíte, jak tipuje X hráčů" (only the total leaks, never the split),
        // but hand the breakdown to the view only when the viewer may see it.
        $distribution = $this->queryBus->handle(new GetMatchPickDistribution(
            competitionId: $competition->id,
            sportMatchId: $sportMatch->id,
        ));
        $pickDistribution = $canSeeDistribution ? $distribution : null;

        // Per-match ranking ("Pořadí za zápas") reveals concrete tips + points, so
        // it needs the others-tips entitlement and a finished match.
        $matchRanking = ($canSeeOthersTips && $sportMatch->isFinished)
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
            'match_events' => $this->matchEventRepository->listByMatch($sportMatch->id),
            'member_rows' => $memberRows,
            'effective_deadline' => $effectiveDeadline,
            'can_see_distribution' => $canSeeDistribution,
            'can_see_others_tips' => $canSeeOthersTips,
            'pick_distribution' => $pickDistribution,
            'tip_count' => $distribution->total,
            'match_ranking' => $matchRanking,
            'deadline_form' => $deadlineForm,
            'current_user_id' => $currentUser->id,
        ]);
    }
}
