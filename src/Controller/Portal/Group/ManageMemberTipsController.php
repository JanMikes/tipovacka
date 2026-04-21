<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Enum\SportMatchState;
use App\Repository\GroupRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Voter\GroupVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{id}/spravovat-tipy',
    name: 'portal_group_manage_member_tips',
    requirements: ['id' => Requirement::UUID],
)]
final class ManageMemberTipsController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly GuessRepository $guessRepository,
        private readonly UserRepository $userRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $group = $this->groupRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(GroupVoter::MANAGE_MEMBERS, $group);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $memberships = $this->membershipRepository->findActiveByGroup($group->id);
        $members = array_map(static fn ($m) => $m->user, $memberships);

        $selectedMemberId = $request->query->get('member');
        $selectedMember = null;
        $rows = [];

        if (\is_string($selectedMemberId) && '' !== $selectedMemberId && Uuid::isValid($selectedMemberId)) {
            $candidate = $this->userRepository->find(Uuid::fromString($selectedMemberId));

            $isMember = null !== $candidate && null !== array_find(
                $members,
                static fn ($m) => $m->id->equals($candidate->id),
            );

            if ($isMember) {
                $selectedMember = $candidate;

                $allMatches = $this->sportMatchRepository->listByTournament(
                    $group->tournament->id,
                    SportMatchState::Scheduled,
                    $now,
                );
                $openMatches = array_values(array_filter(
                    $allMatches,
                    static fn ($m) => $m->isOpenForGuesses && $m->kickoffAt > $now,
                ));

                foreach ($openMatches as $sportMatch) {
                    $rows[] = [
                        'match' => $sportMatch,
                        'guess' => $this->guessRepository->findActiveByUserMatchGroup(
                            $selectedMember->id,
                            $sportMatch->id,
                            $group->id,
                        ),
                    ];
                }
            }
        }

        return $this->render('portal/group/manage_member_tips.html.twig', [
            'group' => $group,
            'members' => $members,
            'selectedMember' => $selectedMember,
            'rows' => $rows,
        ]);
    }
}
