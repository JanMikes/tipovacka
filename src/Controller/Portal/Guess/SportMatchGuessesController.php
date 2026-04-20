<?php

declare(strict_types=1);

namespace App\Controller\Portal\Guess;

use App\Repository\GroupRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Voter\GroupVoter;
use App\Voter\GuessVoter;
use App\Voter\GuessVotingContext;
use App\Voter\SportMatchVoter;
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
    ) {
    }

    public function __invoke(string $groupId, string $sportMatchId): Response
    {
        $group = $this->groupRepository->get(Uuid::fromString($groupId));
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchId));

        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);
        $this->denyAccessUnlessGranted(SportMatchVoter::VIEW, $sportMatch);

        $context = new GuessVotingContext(sportMatch: $sportMatch, groupId: $group->id);
        $this->denyAccessUnlessGranted(GuessVoter::VIEW, $context);

        $memberRows = [];

        if ($this->isGranted(GroupVoter::MANAGE_MEMBERS, $group)) {
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

        return $this->render('portal/guess/detail.html.twig', [
            'group' => $group,
            'sport_match' => $sportMatch,
            'member_rows' => $memberRows,
        ]);
    }
}
