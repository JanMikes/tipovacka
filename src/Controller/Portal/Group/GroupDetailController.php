<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\BulkInvitationFormData;
use App\Form\BulkInvitationFormType;
use App\Form\SendInvitationFormData;
use App\Form\SendInvitationFormType;
use App\Query\GetGroupDetail\GetGroupDetail;
use App\Query\GetGroupLeaderboard\GetGroupLeaderboard;
use App\Query\GetMyGuessesInTournament\GetMyGuessesInTournament;
use App\Query\ListPendingInvitationsForGroup\ListPendingInvitationsForGroup;
use App\Query\ListPendingJoinRequestsForGroup\ListPendingJoinRequestsForGroup;
use App\Query\QueryBus;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Voter\GroupVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{id}',
    name: 'portal_group_detail',
    requirements: ['id' => Requirement::UUID],
)]
final class GroupDetailController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly QueryBus $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        $isAdmin = in_array(UserRole::ADMIN->value, $user->getRoles(), true);

        $detail = $this->queryBus->handle(new GetGroupDetail(
            groupId: $group->id,
            viewerId: $user->id,
            viewerIsAdmin: $isAdmin,
        ));

        $canInvite = $this->isGranted(GroupVoter::INVITE_MEMBER, $group);
        $canManage = $this->isGranted(GroupVoter::MANAGE_MEMBERS, $group);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $pendingInvitations = $canInvite
            ? $this->queryBus->handle(new ListPendingInvitationsForGroup(
                groupId: $group->id,
                now: $now,
            ))
            : [];

        $pendingJoinRequests = ($canManage && $group->tournament->isPublic)
            ? $this->queryBus->handle(new ListPendingJoinRequestsForGroup(
                groupId: $group->id,
            ))
            : [];

        $leaderboard = $this->queryBus->handle(new GetGroupLeaderboard(groupId: $group->id));
        $scoreByUserId = [];
        foreach ($leaderboard->rows as $row) {
            $scoreByUserId[$row->userId->toRfc4122()] = $row;
        }

        $isMember = $this->membershipRepository->hasActiveMembership($user->id, $group->id);
        $myGuesses = $isMember
            ? $this->queryBus->handle(new GetMyGuessesInTournament(
                userId: $user->id,
                tournamentId: $group->tournament->id,
                groupId: $group->id,
            ))
            : [];

        $invitationForm = $this->createForm(SendInvitationFormType::class, new SendInvitationFormData(), [
            'action' => $this->generateUrl('portal_group_invitation_send', ['id' => $group->id->toRfc4122()]),
        ]);

        $bulkInvitationForm = $canManage
            ? $this->createForm(BulkInvitationFormType::class, new BulkInvitationFormData(), [
                'action' => $this->generateUrl('portal_group_invitation_send_bulk', ['id' => $group->id->toRfc4122()]),
            ])
            : null;

        return $this->render('portal/group/detail.html.twig', [
            'group' => $group,
            'detail' => $detail,
            'invitationForm' => $invitationForm->createView(),
            'bulkInvitationForm' => $bulkInvitationForm?->createView(),
            'pendingInvitations' => $pendingInvitations,
            'pendingJoinRequests' => $pendingJoinRequests,
            'score_by_user_id' => $scoreByUserId,
            'my_guesses' => $myGuesses,
            'isMember' => $isMember,
        ]);
    }
}
