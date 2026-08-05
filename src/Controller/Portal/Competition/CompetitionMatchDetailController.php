<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Exception\MatchNotInCompetition;
use App\Form\CompetitionMatchDeadlineFormData;
use App\Form\CompetitionMatchDeadlineFormType;
use App\Query\GetMatchRanking\GetMatchRanking;
use App\Query\GetTeamForm\GetTeamForm;
use App\Query\QueryBus;
use App\Repository\CompetitionMatchSettingRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionTeamFilterRepository;
use App\Repository\GuessEvaluationRepository;
use App\Repository\GuessRepository;
use App\Repository\MatchEventRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipStatsProvider;
use App\Service\Competition\TipVisibilityGate;
use App\Service\EffectiveTipDeadlineResolver;
use App\Value\CompetitionSwitcherOption;
use App\Voter\CompetitionVoter;
use App\Voter\GuessVoter;
use App\Voter\GuessVotingContext;
use App\Voter\SportMatchVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * THE match page (item 22) — one match, inside ONE soutěž.
 *
 * Members, scoring rules and boost entitlements all differ per soutěž, so every
 * number here is scoped to the soutěž in the PATH. That is the whole reason this
 * route, not `/zapasy/{id}`, is where a player lands from the nástěnka, from
 * `/zapasy` and from the soutěž detail; the bare match route is the source owner's
 * page now ({@see \App\Controller\Portal\SportMatch\SportMatchDetailController}).
 *
 * **Switching soutěž.** `<twig:SoutezSwitcher>` is a plain GET `<form>`, so it can
 * only append `?soutez=<id>` — it can never fill a path placeholder. This page
 * therefore accepts that query parameter and **302s to the canonical path-scoped
 * URL** of the chosen soutěž, which keeps the control JS-free and the component
 * unchanged. An unknown, foreign or excluding `?soutez=` is ignored rather than
 * refused: guessing a UUID must never reveal that it exists.
 *
 * The switcher lists the soutěže that INCLUDE this match; B4's „Proč tu nejsou
 * všechny vaše soutěže" panel explains the viewer's soutěže on this same zdroj
 * zápasů that EXCLUDE it. The two sets are disjoint by construction.
 *
 * Nothing here decides visibility on its own: the distribution comes from
 * {@see TipStatsProvider} (one batch, the same provider every match list uses) and
 * others' tips from {@see TipVisibilityGate} — entitlement OR the match having a
 * final RESULT. Managers and admins get no free pass.
 */
#[Route(
    '/souteze/{competitionId}/zapasy/{sportMatchId}',
    name: 'competition_sport_match_detail',
    requirements: ['competitionId' => Requirement::UUID, 'sportMatchId' => Requirement::UUID],
    methods: ['GET'],
)]
#[IsGranted('ROLE_USER')]
final class CompetitionMatchDetailController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
        private readonly GuessEvaluationRepository $guessEvaluationRepository,
        private readonly MatchEventRepository $matchEventRepository,
        private readonly CompetitionMatchSettingRepository $competitionMatchSettingRepository,
        private readonly CompetitionTeamFilterRepository $teamFilterRepository,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly TipVisibilityGate $visibilityGate,
        private readonly TipStatsProvider $tipStatsProvider,
        private readonly QueryBus $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $competitionId, string $sportMatchId, Request $request): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($competitionId));
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchId));

        $this->denyAccessUnlessGranted(CompetitionVoter::VIEW, $competition);
        $this->denyAccessUnlessGranted(SportMatchVoter::VIEW, $sportMatch);

        // The viewer's own soutěže, split by whether they take a tip on this match.
        // One sweep feeds both the switcher and B4's explanation panel.
        [$including, $excluded] = $this->sweepMemberships($currentUser, $sportMatch);

        // The switcher's GET form can only append `?soutez=`, so the query parameter
        // is how a soutěž is chosen — and the canonical URL stays path-scoped.
        $requested = self::pick($including, $request->query->getString('soutez'));

        if (null !== $requested && !$requested->id->equals($competition->id)) {
            return $this->redirectToRoute('competition_sport_match_detail', [
                'competitionId' => $requested->id->toRfc4122(),
                'sportMatchId' => $sportMatch->id->toRfc4122(),
            ]);
        }

        if (!$this->matchProvider->includes($competition, $sportMatch)) {
            throw MatchNotInCompetition::create();
        }

        $context = new GuessVotingContext(sportMatch: $sportMatch, competitionId: $competition->id);
        $this->denyAccessUnlessGranted(GuessVoter::VIEW, $context);

        // A viewer may legitimately be here without a membership row (an admin, or an
        // owner whose row is gone), and the switcher's current option must exist —
        // otherwise it would silently fall back to a soutěž the page is NOT showing.
        if (!self::contains($including, $competition)) {
            array_unshift($including, $competition);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $isCompetitionManager = $this->isGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);
        // Per-viewer window for THIS user's tip-entry surfaces / displayed „Uzávěrka".
        $window = $this->deadlineResolver->windowFor($competition, $sportMatch, $currentUser);
        $effectiveDeadline = $window->deadline;
        // Not yet open is locked too, but the page must say WHY — and the
        // organizer's on-behalf rows must not offer a tip that cannot be stored.
        $isWaiting = $sportMatch->isOpenForGuesses && $window->isWaiting($now);

        // Visibility gate composes THIS viewer's entitlement (premium toggle / own
        // boost — per viewer) with the match's RESULT (once played, public to
        // everyone — the deadline plays no part, 2026-07-30). Distribution and
        // concrete tips are gated independently: an OthersTips buyer sees both, a
        // TipDistribution buyer only the bar.
        $canSeeOthersTips = $this->visibilityGate->canSeeOthersTips($competition, $currentUser, $sportMatch);

        // The distribution surface (bar when entitled, paywall otherwise) comes
        // from the same provider every match list uses — one shape, one component.
        $tipStats = $this->tipStatsProvider->forCompetition($competition, [$sportMatch], $currentUser)[$sportMatch->id->toRfc4122()] ?? null;

        // „Pořadí za zápas" is the ONE list of other members' tips on this page
        // (item 22 folded „Jak tipovali ostatní" into it — same rows, fewer columns).
        $matchRanking = $canSeeOthersTips
            ? $this->queryBus->handle(new GetMatchRanking(
                competitionId: $competition->id,
                sportMatchId: $sportMatch->id,
            ))
            : null;

        $guess = $this->guessRepository->findActiveByUserMatchCompetition(
            $currentUser->id,
            $sportMatch->id,
            $competition->id,
        );
        // The points badge belongs with the viewer's TIP, never with the result —
        // on a finished match the merged card carries both and they must not be
        // mistaken for each other.
        $evaluation = null !== $guess ? $this->guessEvaluationRepository->findByGuess($guess->id) : null;

        return $this->render('portal/competition/match_detail.html.twig', [
            'competition' => $competition,
            'sport_match' => $sportMatch,
            'match_events' => $this->matchEventRepository->listByMatch($sportMatch->id),
            'switcher_competitions' => array_map(self::toSwitcherOption(...), $including),
            'excluded_competitions_for_match_source' => $excluded,
            'member_rows' => $this->memberRows($competition, $sportMatch, $currentUser, $isCompetitionManager, $canSeeOthersTips),
            'effective_deadline' => $effectiveDeadline,
            // The template compares the deadline against THIS, never against Twig's
            // `date()`: that reads the system clock, which is not the app's clock.
            'now' => $now,
            'is_locked' => $this->deadlineResolver->isLocked($competition, $sportMatch, $currentUser, $now),
            'is_waiting' => $isWaiting,
            'opens_at' => $window->opensAt,
            'opening_note' => $window->openingNote,
            'has_guess' => null !== $guess,
            'my_points' => $evaluation?->totalPoints,
            'can_see_others_tips' => $canSeeOthersTips,
            'tip_stats' => $tipStats,
            'match_ranking' => $matchRanking,
            // „ARG · V2 R0 P0" — both teams in ONE query, counted over the finished
            // matches THIS soutěž includes, so the record agrees with every other
            // figure on the page.
            'team_form' => $this->queryBus->handle(new GetTeamForm(
                competitionId: $competition->id,
                teamIds: [$sportMatch->homeTeam->id, $sportMatch->awayTeam->id],
            )),
            'deadline_form' => $this->deadlineForm($competition, $sportMatch),
            'current_user_id' => $currentUser->id,
        ]);
    }

    /**
     * The viewer's active soutěže, split in two disjoint sets: the ones that INCLUDE
     * this match (the switcher's options — the soutěž in the path is always among
     * them once `includes()` has passed, and is prepended for a viewer who reaches
     * the page without a membership, e.g. an admin) and the ones on the SAME zdroj
     * zápasů that exclude it, with a reason (B4).
     *
     * @return array{0: list<Competition>, 1: list<array{id: Uuid, name: string, reason: string, teams: list<\App\Value\TeamView>}>}
     */
    private function sweepMemberships(User $user, SportMatch $sportMatch): array
    {
        /** @var list<Competition> $including */
        $including = [];
        $excluded = [];

        foreach ($this->membershipRepository->findMyActive($user->id) as $membership) {
            $competition = $membership->competition;

            if ($this->matchProvider->includes($competition, $sportMatch)) {
                $including[] = $competition;

                continue;
            }

            // B4: a soutěž over ANOTHER zdroj zápasů has obviously nothing to do
            // with this match. A soutěž over THIS one, though, raises the „I am a
            // member — why is it not listed?" question, and the viewer cannot
            // answer it from this page. Collect those and say why.
            if ($competition->matchSource->id->equals($sportMatch->matchSource->id)) {
                $excluded[] = [
                    'id' => $competition->id,
                    'name' => $competition->name,
                    'reason' => self::exclusionReason($competition, $sportMatch),
                    // Bounded by the viewer's own Teams-mode soutěže over this one
                    // zdroj — typically zero or one, so no N+1 risk.
                    'teams' => CompetitionMatchSelectionMode::Teams === $competition->selectionMode
                        ? $this->teamFilterRepository->teamViewsFor($competition->id)
                        : [],
                ];
            }
        }

        return [$including, $excluded];
    }

    /**
     * The soutěž `?soutez=` asked for — only when the viewer is in it AND it takes a
     * tip on this match. Anything else returns null, which leaves the page on the
     * soutěž in the path instead of 403-ing.
     *
     * @param list<Competition> $including
     */
    private static function pick(array $including, string $requestedId): ?Competition
    {
        if ('' === $requestedId) {
            return null;
        }

        foreach ($including as $competition) {
            if ($competition->id->toRfc4122() === $requestedId) {
                return $competition;
            }
        }

        return null;
    }

    /**
     * @param list<Competition> $competitions
     */
    private static function contains(array $competitions, Competition $needle): bool
    {
        foreach ($competitions as $competition) {
            if ($competition->id->equals($needle->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * „Tipy členů" — the organizer's on-behalf rows. Managing a member's tip must
     * not reveal it: the manager sees only WHETHER it is filled (and may overwrite
     * it) unless they are entitled to others' tips here, or it is their own row.
     *
     * @return list<array{user: User, hasGuess: bool, guess: ?\App\Entity\Guess}>
     */
    private function memberRows(
        Competition $competition,
        SportMatch $sportMatch,
        User $currentUser,
        bool $isCompetitionManager,
        bool $canSeeOthersTips,
    ): array {
        if (!$isCompetitionManager) {
            return [];
        }

        $rows = [];

        foreach ($this->membershipRepository->findActiveByCompetition($competition->id) as $membership) {
            $guess = $this->guessRepository->findActiveByUserMatchCompetition(
                $membership->user->id,
                $sportMatch->id,
                $competition->id,
            );
            $isOwnRow = $membership->user->id->equals($currentUser->id);

            $rows[] = [
                'user' => $membership->user,
                'hasGuess' => null !== $guess,
                'guess' => ($canSeeOthersTips || $isOwnRow) ? $guess : null,
            ];
        }

        return $rows;
    }

    private function deadlineForm(Competition $competition, SportMatch $sportMatch): ?\Symfony\Component\Form\FormView
    {
        if (!$this->isGranted(CompetitionVoter::EDIT, $competition)) {
            return null;
        }

        $existingSetting = $this->competitionMatchSettingRepository->findByCompetitionAndMatch(
            $competition->id,
            $sportMatch->id,
        );

        return $this->createForm(
            CompetitionMatchDeadlineFormType::class,
            CompetitionMatchDeadlineFormData::fromSetting($existingSetting),
            [
                'action' => $this->generateUrl('competition_sport_match_set_deadline', [
                    'competitionId' => $competition->id->toRfc4122(),
                    'sportMatchId' => $sportMatch->id->toRfc4122(),
                ]),
                // „Tipování otevřeno od" is admin-only — a manager's form does
                // not carry the fields, and their save leaves them untouched.
                'with_opening' => $this->isGranted('ROLE_ADMIN'),
            ],
        )->createView();
    }

    private static function toSwitcherOption(Competition $competition): CompetitionSwitcherOption
    {
        return CompetitionSwitcherOption::fromDates(
            id: $competition->id->toRfc4122(),
            name: $competition->name,
            subtitle: $competition->matchSource->name,
            startAt: $competition->matchSource->startAt,
            endAt: $competition->matchSource->endAt,
            isFinished: $competition->scheduleIsComplete,
        );
    }

    /**
     * Why {@see CompetitionMatchProvider::includes} said no, for a soutěž that lives
     * on the very zdroj zápasů this match belongs to. The cases mirror the provider's
     * own branches one-to-one — the deleted-match and foreign-source ones cannot
     * occur here (the page 404s on the former, the caller filters the latter), hence
     * the `other` catch-all rather than a partial match.
     */
    private static function exclusionReason(Competition $competition, SportMatch $sportMatch): string
    {
        return match (true) {
            CompetitionMatchSelectionMode::Subset === $competition->selectionMode => 'subset',
            CompetitionMatchSelectionMode::Teams === $competition->selectionMode => 'teams',
            $sportMatch->isPlayoff && !$competition->includePlayoff => 'playoff',
            default => 'other',
        };
    }
}
