<?php

declare(strict_types=1);

namespace App\Controller\Portal\Guess;

use App\Entity\User;
use App\Form\GroupMatchDeadlineFormData;
use App\Form\GroupMatchDeadlineFormType;
use App\Repository\GroupMatchSettingRepository;
use App\Repository\GroupRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Service\EffectiveTipDeadlineResolver;
use App\Voter\GroupVoter;
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
    '/portal/skupiny/{groupId}/zapasy/{sportMatchId}',
    name: 'portal_group_sport_match_guesses',
    requirements: ['groupId' => Requirement::UUID, 'sportMatchId' => Requirement::UUID],
)]
final class SportMatchGuessesController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
        private readonly GroupMatchSettingRepository $groupMatchSettingRepository,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $groupId, string $sportMatchId): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($groupId));
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchId));

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);
        $this->denyAccessUnlessGranted(SportMatchVoter::VIEW, $sportMatch);

        $context = new GuessVotingContext(sportMatch: $sportMatch, groupId: $group->id);
        $this->denyAccessUnlessGranted(GuessVoter::VIEW, $context);

        $isGroupManager = $this->isGranted(GroupVoter::MANAGE_MEMBERS, $group);
        $effectiveDeadline = $this->deadlineResolver->resolve($group, $sportMatch);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $canSeeAllTips = $isGroupManager
            || !$group->hideOthersTipsBeforeDeadline
            || $now >= $effectiveDeadline;

        $memberRows = [];

        if ($isGroupManager) {
            $memberships = $this->membershipRepository->findActiveByGroup($group->id);
            foreach ($memberships as $membership) {
                $guess = $this->guessRepository->findActiveByUserMatchGroup(
                    $membership->user->id,
                    $sportMatch->id,
                    $group->id,
                );
                $memberRows[] = [
                    'user' => $membership->user,
                    'guess' => $guess,
                ];
            }
        }

        $deadlineForm = null;

        if ($this->isGranted(GroupVoter::EDIT, $group)) {
            $existingSetting = $this->groupMatchSettingRepository->findByGroupAndMatch(
                $group->id,
                $sportMatch->id,
            );
            $deadlineForm = $this->createForm(
                GroupMatchDeadlineFormType::class,
                GroupMatchDeadlineFormData::fromSetting($existingSetting),
                [
                    'action' => $this->generateUrl('portal_group_sport_match_set_deadline', [
                        'groupId' => $group->id->toRfc4122(),
                        'sportMatchId' => $sportMatch->id->toRfc4122(),
                    ]),
                ],
            )->createView();
        }

        return $this->render('portal/guess/detail.html.twig', [
            'group' => $group,
            'sport_match' => $sportMatch,
            'member_rows' => $memberRows,
            'effective_deadline' => $effectiveDeadline,
            'can_see_all_tips' => $canSeeAllTips,
            'deadline_form' => $deadlineForm,
            'current_user_id' => $currentUser->id,
        ]);
    }
}
