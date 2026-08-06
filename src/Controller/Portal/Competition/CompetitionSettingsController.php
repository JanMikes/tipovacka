<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Entity\User;
use App\Enum\CompetitionMonetization;
use App\Enum\UserRole;
use App\Form\BulkInvitationFormData;
use App\Form\BulkInvitationFormType;
use App\Form\SendInvitationFormData;
use App\Form\SendInvitationFormType;
use App\Query\GetCompetitionDetail\GetCompetitionDetail;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\GetCompetitionRuleConfiguration\GetCompetitionRuleConfiguration;
use App\Query\ListPendingInvitationsForCompetition\ListPendingInvitationsForCompetition;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Service\Credits\PricingConfig;
use App\Voter\CompetitionVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * „Nastavení soutěže" — the single destination every organizer control moved to
 * when item 08 turned the competition detail into a pure playing surface.
 *
 * It absorbs the small forms inline (členové, pozvánky e-mailem, PIN + odkaz,
 * read-only pravidla) and links out only to the genuinely large ones that keep
 * their own page (upravit, pravidla bodování, výběr zápasů / týmy, prémium).
 *
 * Reachable by any VIEWER of the competition — a plain member sees the roster and
 * the scoring rules, nothing more; every management block is gated by its own
 * voter, exactly as it was on the old detail page. The „Nastavení" button in the
 * detail action bar is shown only to `competition_edit`.
 */
#[Route(
    '/souteze/{id}/nastaveni',
    name: 'competition_settings',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
#[IsGranted('ROLE_USER')]
final class CompetitionSettingsController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MembershipRepository $membershipRepository,
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

        // MANAGE_JOIN_MECHANICS, not INVITE_MEMBER: since every member may invite, the
        // latter no longer describes „owns this section". The organizer's pending-invitation
        // list, the bulk form and the PIN/link controls are one surface and share one gate —
        // the very one `settings.html.twig` wraps the section in.
        $canInvite = $this->isGranted(CompetitionVoter::MANAGE_JOIN_MECHANICS, $competition);
        $canManage = $this->isGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $pendingInvitations = $canInvite
            ? $this->queryBus->handle(new ListPendingInvitationsForCompetition(
                competitionId: $competition->id,
                now: $now,
            ))
            : [];

        $invitationForm = $this->createForm(SendInvitationFormType::class, new SendInvitationFormData(), [
            'action' => $this->generateUrl('competition_invitation_send', ['id' => $competition->id->toRfc4122()]),
        ]);

        $bulkInvitationForm = $canManage
            ? $this->createForm(BulkInvitationFormType::class, new BulkInvitationFormData(), [
                'action' => $this->generateUrl('competition_invitation_send_bulk', ['id' => $competition->id->toRfc4122()]),
            ])
            : null;

        $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard(competitionId: $competition->id));
        $scoreByUserId = [];

        foreach ($leaderboard->rows as $row) {
            $scoreByUserId[$row->userId->toRfc4122()] = $row;
        }

        $ruleConfiguration = $this->queryBus->handle(new GetCompetitionRuleConfiguration(
            competitionId: $competition->id,
        ));

        // „Zapnout prémium" charges the manager PREMIUM_PER_PLAYER per active
        // non-owner member immediately — the confirm modal discloses the total.
        $premiumEnableMemberCount = CompetitionMonetization::Premium === $competition->monetization
            ? 0
            : $this->membershipRepository->countActiveNonOwnerMembers($competition->id, $competition->owner->id);

        return $this->render('portal/competition/settings.html.twig', [
            'competition' => $competition,
            'detail' => $detail,
            'premium_enable_member_count' => $premiumEnableMemberCount,
            'premium_per_player' => PricingConfig::PREMIUM_PER_PLAYER,
            'invitationForm' => $invitationForm->createView(),
            'bulkInvitationForm' => $bulkInvitationForm?->createView(),
            'pendingInvitations' => $pendingInvitations,
            'score_by_user_id' => $scoreByUserId,
            'rule_items' => $ruleConfiguration->items,
        ]);
    }
}
