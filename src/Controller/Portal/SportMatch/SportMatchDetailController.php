<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Query\GetMatchRanking\GetMatchRanking;
use App\Query\GetTeamForm\GetTeamForm;
use App\Query\QueryBus;
use App\Repository\CompetitionTeamFilterRepository;
use App\Repository\GuessRepository;
use App\Repository\MatchEventRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipStatsProvider;
use App\Service\Competition\TipVisibilityGate;
use App\Service\EffectiveTipDeadlineResolver;
use App\Value\CompetitionSwitcherOption;
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
 * Match detail (item 10) — the place where ONE match is fully understood: hero,
 * tip form, „Rozložení tipů", „Průběh zápasu" and „Pořadí za zápas".
 *
 * A match can belong to SEVERAL of the viewer's soutěže, and members, scoring
 * rules and boost entitlements all differ per soutěž — so every number on the
 * page is scoped to exactly one, chosen with `<twig:SoutezSwitcher>` and carried
 * in `?soutez={uuid}`. The switcher lists only the soutěže that INCLUDE this
 * match; B4's „Proč tu nejsou všechny vaše soutěže" panel below explains the ones
 * that EXCLUDE it. The two sets are disjoint by construction, so a viewer is
 * never told both things about the same soutěž.
 *
 * An unknown or foreign `?soutez=` falls back to the first including soutěž
 * instead of 403-ing — guessing a UUID must never reveal that it exists.
 *
 * Nothing here decides visibility on its own: the distribution comes from
 * {@see TipStatsProvider} (the one batched path every match list uses) and the
 * ranking from {@see TipVisibilityGate}, which composes the viewer's entitlement
 * with the match's userless deadline.
 */
#[Route(
    '/zapasy/{id}',
    name: 'sport_match_detail',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
#[IsGranted('ROLE_USER')]
final class SportMatchDetailController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
        private readonly MatchEventRepository $matchEventRepository,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly TipStatsProvider $tipStatsProvider,
        private readonly TipVisibilityGate $visibilityGate,
        private readonly CompetitionTeamFilterRepository $teamFilterRepository,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly QueryBus $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::VIEW, $sportMatch);

        /** @var User $user */
        $user = $this->getUser();
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        /** @var list<Competition> $including */
        $including = [];
        $excludedCompetitions = [];

        foreach ($this->membershipRepository->findMyActive($user->id) as $membership) {
            $competition = $membership->competition;

            if ($this->matchProvider->includes($competition, $sportMatch)) {
                $including[] = $competition;

                continue;
            }

            // B4: a competition over ANOTHER source has obviously nothing to
            // do with this match. A competition over THIS source, though,
            // raises the „I am a member — why is it not listed?" question,
            // and the user cannot answer it from this page. Collect those
            // and say why the match falls outside their scope.
            if ($competition->matchSource->id->equals($sportMatch->matchSource->id)) {
                $excludedCompetitions[] = [
                    'id' => $competition->id,
                    'name' => $competition->name,
                    'reason' => $this->exclusionReason($competition, $sportMatch),
                    // Bounded by the viewer's own Teams-mode competitions over
                    // this one source — typically zero or one, so no N+1 risk.
                    'teams' => CompetitionMatchSelectionMode::Teams === $competition->selectionMode
                        ? $this->teamFilterRepository->teamViewsFor($competition->id)
                        : [],
                ];
            }
        }

        $selected = self::resolveSelected($including, $request->query->getString('soutez'));

        $view = [
            'sport_match' => $sportMatch,
            'match_events' => $this->matchEventRepository->listByMatch($sportMatch->id),
            'switcher_competitions' => array_map(self::toSwitcherOption(...), $including),
            'selected_competition' => $selected,
            'excluded_competitions_for_match_source' => $excludedCompetitions,
            'tip_stats' => null,
            'has_guess' => false,
            'is_locked' => false,
            'effective_deadline' => null,
            'can_see_others_tips' => false,
            'match_ranking' => null,
            'team_form' => null,
        ];

        if (null === $selected) {
            return $this->render('portal/sport_match/detail.html.twig', $view);
        }

        // One batched resolution for the distribution surface — the same provider
        // (and the same component) every match list uses. Never a per-row query.
        $view['tip_stats'] = $this->tipStatsProvider
            ->forCompetition($selected, [$sportMatch], $user)[$sportMatch->id->toRfc4122()] ?? null;

        $guess = $this->guessRepository->findActiveByUserMatchCompetition(
            $user->id,
            $sportMatch->id,
            $selected->id,
        );
        $view['has_guess'] = null !== $guess;
        // B5: an unfilled tip in a locked soutěž is „Netipováno" — a fact, not a
        // call to action.
        $view['is_locked'] = $this->deadlineResolver->isLocked($selected, $sportMatch, $user, $now);
        $view['effective_deadline'] = $this->deadlineResolver->deadlineFor($selected, $sportMatch, $user);

        // „Pořadí za zápas" reveals other players' concrete tips, which is exactly
        // what BoostType::OthersTips sells — so the gate, never a hand-rolled check.
        $canSeeOthersTips = $this->visibilityGate->canSeeOthersTips($selected, $user, $sportMatch);
        $view['can_see_others_tips'] = $canSeeOthersTips;
        $view['match_ranking'] = $canSeeOthersTips
            ? $this->queryBus->handle(new GetMatchRanking(
                competitionId: $selected->id,
                sportMatchId: $sportMatch->id,
            ))
            : null;

        // „ARG · V2 R0 P0" — both teams in one query, scoped to what this soutěž
        // includes so the record agrees with every other figure on the page.
        $view['team_form'] = $this->queryBus->handle(new GetTeamForm(
            competitionId: $selected->id,
            teamIds: [$sportMatch->homeTeam->id, $sportMatch->awayTeam->id],
        ));

        return $this->render('portal/sport_match/detail.html.twig', $view);
    }

    /**
     * The soutěž in focus: the requested one when it includes this match and the
     * viewer is in it, otherwise the first including one (`findMyActive` is ordered
     * most-recently-joined first). Falling back rather than 403-ing is the same
     * leak prevention the nástěnka and the žebříček apply.
     *
     * @param list<Competition> $including
     */
    private static function resolveSelected(array $including, string $requestedId): ?Competition
    {
        foreach ($including as $competition) {
            if ($competition->id->toRfc4122() === $requestedId) {
                return $competition;
            }
        }

        return $including[0] ?? null;
    }

    private static function toSwitcherOption(Competition $competition): CompetitionSwitcherOption
    {
        return CompetitionSwitcherOption::fromDates(
            id: $competition->id->toRfc4122(),
            name: $competition->name,
            subtitle: $competition->matchSource->name,
            startAt: $competition->matchSource->startAt,
            endAt: $competition->matchSource->endAt,
            isFinished: $competition->matchSource->isCompleted,
        );
    }

    /**
     * Why {@see CompetitionMatchProvider::includes} said no, for a competition
     * that lives on the very source this match belongs to. The cases mirror the
     * provider's own branches one-to-one — the deleted-match and foreign-source
     * ones cannot occur here (the page 404s on the former, the caller filters
     * the latter), hence the `other` catch-all rather than a partial match.
     */
    private function exclusionReason(Competition $competition, SportMatch $sportMatch): string
    {
        return match (true) {
            CompetitionMatchSelectionMode::Subset === $competition->selectionMode => 'subset',
            CompetitionMatchSelectionMode::Teams === $competition->selectionMode => 'teams',
            $sportMatch->isPlayoff && !$competition->includePlayoff => 'playoff',
            default => 'other',
        };
    }
}
