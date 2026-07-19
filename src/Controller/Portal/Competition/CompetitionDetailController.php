<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\BulkInvitationFormData;
use App\Form\BulkInvitationFormType;
use App\Form\SendInvitationFormData;
use App\Form\SendInvitationFormType;
use App\Query\GetCompetitionDetail\GetCompetitionDetail;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\GetCompetitionRuleConfiguration\GetCompetitionRuleConfiguration;
use App\Query\GetMyGuessesInMatchSource\GetMyGuessesInMatchSource;
use App\Query\ListPendingInvitationsForCompetition\ListPendingInvitationsForCompetition;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Service\EffectiveTipDeadlineResolver;
use App\Voter\CompetitionVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}',
    name: 'portal_competition_detail',
    requirements: ['id' => Requirement::UUID],
)]
final class CompetitionDetailController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly QueryBus $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::VIEW, $competition);

        $isAdmin = in_array(UserRole::ADMIN->value, $user->getRoles(), true);

        $detail = $this->queryBus->handle(new GetCompetitionDetail(
            competitionId: $competition->id,
            viewerId: $user->id,
            viewerIsAdmin: $isAdmin,
        ));

        $canInvite = $this->isGranted(CompetitionVoter::INVITE_MEMBER, $competition);
        $canManage = $this->isGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $pendingInvitations = $canInvite
            ? $this->queryBus->handle(new ListPendingInvitationsForCompetition(
                competitionId: $competition->id,
                now: $now,
            ))
            : [];

        $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard(competitionId: $competition->id));
        $scoreByUserId = [];
        foreach ($leaderboard->rows as $row) {
            $scoreByUserId[$row->userId->toRfc4122()] = $row;
        }

        $isMember = $this->membershipRepository->hasActiveMembership($user->id, $competition->id);
        $myGuesses = $isMember
            ? $this->queryBus->handle(new GetMyGuessesInMatchSource(
                userId: $user->id,
                matchSourceId: $competition->matchSource->id,
                competitionId: $competition->id,
            ))
            : [];

        $invitationForm = $this->createForm(SendInvitationFormType::class, new SendInvitationFormData(), [
            'action' => $this->generateUrl('portal_competition_invitation_send', ['id' => $competition->id->toRfc4122()]),
        ]);

        $bulkInvitationForm = $canManage
            ? $this->createForm(BulkInvitationFormType::class, new BulkInvitationFormData(), [
                'action' => $this->generateUrl('portal_competition_invitation_send_bulk', ['id' => $competition->id->toRfc4122()]),
            ])
            : null;

        $ruleConfiguration = $this->queryBus->handle(new GetCompetitionRuleConfiguration(
            competitionId: $competition->id,
        ));

        // Tip-locking state for the hero + management buttons: locked = the
        // competition-level lock moment (manual lock or first kickoff) passed;
        // a manual lock can be undone only before the first kickoff.
        $lockMoment = $this->deadlineResolver->lockMomentFor($competition);
        $firstKickoffAt = $this->deadlineResolver->firstKickoffFor($competition);
        $tipsLocked = null !== $lockMoment && $lockMoment <= $now;
        $canUnlockTips = null !== $competition->tipsLockedAt
            && (null === $firstKickoffAt || $now < $firstKickoffAt);

        return $this->render('portal/competition/detail.html.twig', [
            'competition' => $competition,
            'detail' => $detail,
            'lock_moment' => $lockMoment,
            'tips_locked' => $tipsLocked,
            'can_unlock_tips' => $canUnlockTips,
            'invitationForm' => $invitationForm->createView(),
            'bulkInvitationForm' => $bulkInvitationForm?->createView(),
            'pendingInvitations' => $pendingInvitations,
            'score_by_user_id' => $scoreByUserId,
            'my_guesses' => $myGuesses,
            'isMember' => $isMember,
            'rule_items' => $ruleConfiguration->items,
        ]);
    }
}
