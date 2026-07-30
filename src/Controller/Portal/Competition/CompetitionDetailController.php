<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMonetization;
use App\Enum\SportMatchState;
use App\Enum\UserRole;
use App\Query\GetCompetitionDetail\GetCompetitionDetail;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\GetCompetitionRuleConfiguration\GetCompetitionRuleConfiguration;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Repository\GuessEvaluationRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\CompetitionRoundResolver;
use App\Service\Competition\TipStatsProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Voter\CompetitionVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * The competition hub — a **playing surface** since item 08: header + action bar,
 * the „Tipněte si všechny zápasy najednou" banner, the match list, and an aside
 * with the žebříček and the boost benefits. Every organizer control lives behind
 * „Nastavení" ({@see CompetitionSettingsController}).
 */
#[Route(
    '/souteze/{id}',
    name: 'competition_detail',
    requirements: ['id' => Requirement::UUID],
)]
#[IsGranted('ROLE_USER')]
final class CompetitionDetailController extends AbstractController
{
    /** How many žebříček rows the aside panel shows before „Celý žebříček". */
    private const int LEADERBOARD_PREVIEW_ROWS = 7;

    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
        private readonly GuessEvaluationRepository $evaluationRepository,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly CompetitionRoundResolver $roundResolver,
        private readonly TipStatsProvider $tipStatsProvider,
        private readonly QueryBus $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::VIEW, $competition);

        $isAdmin = in_array(UserRole::ADMIN->value, $user->getRoles(), true);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $detail = $this->queryBus->handle(new GetCompetitionDetail(
            competitionId: $competition->id,
            viewerId: $user->id,
            viewerIsAdmin: $isAdmin,
        ));

        $membership = $this->membershipRepository->findActiveMembership($user->id, $competition->id);
        $isMember = null !== $membership;
        $isFullyOver = $this->matchProvider->isFullyOver($competition);

        // ── The first-visit boost-price modal (item 19, closes B10) ─────────
        // Five suppressions, all deliberate:
        //   • not a member        — nothing to sell them, and nothing to stamp
        //     (only an admin ever reaches this page as a non-member);
        //   • already dismissed   — the stamp lives on the membership, so the
        //     dismissal survives a fresh session by construction;
        //   • monetization ≠ boosts — on a PREMIUM competition the player cannot
        //     buy boosts at all (the organizer pays for everyone), so a price
        //     list would advertise the unpurchasable; `none` sells nothing either.
        //     Premium XOR boosts, as a user-visible consequence;
        //   • fully over (B6)     — a boost bought now could unlock nothing;
        //   • just joined (item 28) — every join redirect carries `?pripojeno=1`,
        //     so we do not greet a brand-new member with a price list in the same
        //     breath as the welcome. Nothing is stamped for a suppressed render
        //     (only DismissBoostIntroController writes boostIntroSeenAt), so the
        //     modal simply waits for the next visit. A forged or shared parameter
        //     therefore costs one render of a promotional modal — hence no token,
        //     no signature, no session check.
        $justJoined = $request->query->getBoolean('pripojeno');

        $showBoostIntro = $isMember
            && null === $membership->boostIntroSeenAt
            && CompetitionMonetization::Boosts === $competition->monetization
            && !$isFullyOver
            && !$justJoined;

        // Tip-locking state for the hero + the „Uzamknout tipy" action: locked =
        // the competition-level lock moment (manual lock or first kickoff)
        // passed; a manual lock can be undone only before the first kickoff.
        $lockMoment = $this->deadlineResolver->lockMomentFor($competition);
        $firstKickoffAt = $this->deadlineResolver->firstKickoffFor($competition);
        $tipsLocked = null !== $lockMoment && $lockMoment <= $now;
        $canUnlockTips = null !== $competition->tipsLockedAt
            && (null === $firstKickoffAt || $now < $firstKickoffAt);

        // B2: a manual lock moment still ahead is a SCHEDULE — the page shows it
        // and offers „změnit"/„zrušit" until it fires (after which the branch
        // above turns into the plain locked state).
        $scheduledLockAt = null !== $competition->tipsLockedAt && $competition->tipsLockedAt > $now
            ? $competition->tipsLockedAt
            : null;

        // ── „Pravidla" (item 26) ────────────────────────────────────────────
        // The very same read model the settings page renders, shown here in a
        // dialog EVERY viewer can open: scoring rules are what a player needs to
        // understand their points, so the button is behind no organizer voter.
        // Configuration only — a rules modal never reveals anybody's tips.
        $ruleConfiguration = $this->queryBus->handle(new GetCompetitionRuleConfiguration(
            competitionId: $competition->id,
        ));

        // ── The match list ──────────────────────────────────────────────────
        // Scope comes from CompetitionMatchProvider (never re-derived), the
        // per-match deadline from EffectiveTipDeadlineResolver, and „Rozložení
        // tipů" from TipStatsProvider — batched for the whole page, never per row.
        $matches = $this->matchProvider->matchesFor($competition);
        $deadlines = $this->deadlineResolver->deadlinesFor($competition, $matches, $user);
        $guessesByMatch = $this->guessRepository->activeByUserInCompetitionIndexedByMatch($user->id, $competition->id);
        $pointsByMatch = $this->evaluationRepository->pointsByMatchForUserInCompetition($user->id, $competition->id);
        $tipStats = $this->tipStatsProvider->forCompetition($competition, $matches, $user);

        $matchRows = [];

        foreach ($matches as $match) {
            $key = $match->id->toRfc4122();
            $deadline = $deadlines[$key] ?? $match->kickoffAt;
            $guess = $guessesByMatch[$key] ?? null;

            $matchRows[] = [
                'match' => $match,
                'guess' => $guess,
                'deadline' => $deadline,
                'isOpen' => $match->isOpenForGuesses && $now < $deadline,
                'points' => $pointsByMatch[$key] ?? null,
                'stats' => $tipStats[$key] ?? null,
                'state' => $this->rowState($match, null !== $guess, $match->isOpenForGuesses && $now < $deadline),
            ];
        }

        // ── The žebříček aside ──────────────────────────────────────────────
        $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard(competitionId: $competition->id));
        $previewRows = array_slice($leaderboard->rows, 0, self::LEADERBOARD_PREVIEW_ROWS);
        $myRow = null;

        foreach ($leaderboard->rows as $index => $row) {
            if ($row->userId->equals($user->id) && $index >= self::LEADERBOARD_PREVIEW_ROWS) {
                $myRow = $row;

                break;
            }
        }

        return $this->render('portal/competition/detail.html.twig', [
            'competition' => $competition,
            'detail' => $detail,
            'rule_items' => $ruleConfiguration->items,
            'lock_moment' => $lockMoment,
            'tips_locked' => $tipsLocked,
            'can_unlock_tips' => $canUnlockTips,
            'scheduled_lock_at' => $scheduledLockAt,
            'first_kickoff_at' => $firstKickoffAt,
            'now' => $now,
            'is_member' => $isMember,
            'is_owner' => $user->id->equals($competition->owner->id),
            'is_over' => $isFullyOver,
            'show_boost_intro' => $showBoostIntro,
            'boost_types' => BoostType::cases(),
            'has_started' => null !== $firstKickoffAt && $firstKickoffAt <= $now,
            'current_round' => $this->roundResolver->currentRound($competition),
            'match_rows' => $matchRows,
            'leaderboard_rows' => $previewRows,
            'leaderboard_total' => count($leaderboard->rows),
            'leaderboard_my_row' => $myRow,
        ]);
    }

    /**
     * The `Match:MatchRow` state for one row: finished (result in), locked (no
     * longer tippable), tipped (a tip is in and the window is still open) or open.
     */
    private function rowState(SportMatch $match, bool $hasGuess, bool $isOpen): string
    {
        if (SportMatchState::Finished === $match->state) {
            return 'finished';
        }

        if (!$isOpen) {
            return 'locked';
        }

        return $hasGuess ? 'tipped' : 'open';
    }
}
