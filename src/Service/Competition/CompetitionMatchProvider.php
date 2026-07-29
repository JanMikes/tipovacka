<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionTeamFilter;
use App\Entity\SportMatch;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\SportMatchState;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionTeamFilterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * THE single authority answering "which matches belong to competition C"
 * (and "does match M belong to C").
 *
 * - mode All ⇒ all source matches, minus playoff matches when
 *   `includePlayoff = false`, minus deleted matches;
 * - mode Subset ⇒ explicitly selected matches only (selection wins over
 *   `includePlayoff` — an explicitly selected playoff match counts).
 * - mode Teams ⇒ every source match where a filter team plays (home OR away),
 *   dynamically — a team match added later auto-joins; playoff always counts.
 *
 * Read queries compose the same semantics via {@see applyCompetitionMatchFilter}
 * (competition-scoped) or {@see applyRowLevelCompetitionMatchFilter}
 * (cross-competition row-wise variant).
 */
class CompetitionMatchProvider implements ResetInterface
{
    /** @var array<string, array<string, true>> competition UUID → set of selected match UUIDs */
    private array $selectionCache = [];

    /** @var array<string, array<string, true>> competition UUID → set of filter team UUIDs */
    private array $filterTeamCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompetitionRepository $competitionRepository,
        private readonly CompetitionMatchSelectionRepository $selectionRepository,
        private readonly CompetitionTeamFilterRepository $teamFilterRepository,
    ) {
    }

    /**
     * All matches belonging to the competition, kickoff-ordered. Includes every
     * state (Scheduled / Live / Finished / Postponed / Cancelled) — state
     * filtering stays with the call sites.
     *
     * @return list<SportMatch>
     */
    public function matchesFor(Competition $competition): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC');

        $this->applyCompetitionMatchFilter($qb, 'm', $competition);

        /** @var list<SportMatch> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Whether the competition still has an INCLUDED match that is not settled —
     * still Scheduled, Live or Postponed, i.e. a result that may yet move the
     * standings. Finished (evaluated synchronously on finish) and Cancelled (no
     * result) count as settled. The `competition_ended` gate uses this so final
     * standings never fire while a match is still to be played.
     */
    public function hasUnsettledMatches(Competition $competition): bool
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(SportMatch::class, 'm')
            ->andWhere('m.state IN (:cmp_unsettled_states)')
            ->setParameter('cmp_unsettled_states', [
                SportMatchState::Scheduled,
                SportMatchState::Live,
                SportMatchState::Postponed,
            ]);

        $this->applyCompetitionMatchFilter($qb, 'm', $competition);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Whether the competition is **fully over**: it includes at least one match
     * and none of its included matches can still produce a result — no Scheduled,
     * Live or Postponed one is left. Finished matches carry a final result,
     * Cancelled ones never will, so both count as settled; a competition with an
     * empty scope is NOT over (there is nothing to have ended).
     *
     * Deliberately NOT „past the last kickoff": a kicked-off match without an
     * entered result still has standings to move. Scope always comes from this
     * service, so every selection mode answers consistently. See
     * .docs/DOMAIN.md §Monetization („fully over") — B6 uses it to stop boost
     * purchases that could no longer unlock anything.
     */
    public function isFullyOver(Competition $competition): bool
    {
        return $this->matchCount($competition) > 0 && !$this->hasUnsettledMatches($competition);
    }

    /** How many matches the competition includes (any state, deleted excluded). */
    public function matchCount(Competition $competition): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(SportMatch::class, 'm');

        $this->applyCompetitionMatchFilter($qb, 'm', $competition);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function includes(Competition $competition, SportMatch $sportMatch): bool
    {
        if (null !== $sportMatch->deletedAt) {
            return false;
        }

        return $this->includesIgnoringDeletion($competition, $sportMatch);
    }

    /**
     * Same membership test as {@see includes} but WITHOUT the deleted-match
     * short-circuit — for reasoning about a match's membership as it was BEFORE
     * a soft-delete (the SportMatchDeleted lock-moment pin). Live read paths
     * always use {@see includes}.
     */
    public function includesIgnoringDeletion(Competition $competition, SportMatch $sportMatch): bool
    {
        if (!$sportMatch->matchSource->id->equals($competition->matchSource->id)) {
            return false;
        }

        if (CompetitionMatchSelectionMode::Subset === $competition->selectionMode) {
            return isset($this->selectedMatchIdSet($competition->id)[$sportMatch->id->toRfc4122()]);
        }

        if (CompetitionMatchSelectionMode::Teams === $competition->selectionMode) {
            $teamIds = $this->filterTeamIdSet($competition->id);

            return isset($teamIds[$sportMatch->homeTeam->id->toRfc4122()])
                || isset($teamIds[$sportMatch->awayTeam->id->toRfc4122()]);
        }

        return $competition->includePlayoff || !$sportMatch->isPlayoff;
    }

    /**
     * Constrains `$matchAlias` (a SportMatch alias in `$qb`) to the matches
     * belonging to the given competition.
     */
    public function applyCompetitionMatchFilter(QueryBuilder $qb, string $matchAlias, Competition|Uuid $competition): void
    {
        if ($competition instanceof Uuid) {
            $competition = $this->competitionRepository->get($competition);
        }

        $qb->andWhere(sprintf('%s.matchSource = :cmp_source_id', $matchAlias))
            ->andWhere(sprintf('%s.deletedAt IS NULL', $matchAlias))
            ->setParameter('cmp_source_id', $competition->matchSource->id);

        if (CompetitionMatchSelectionMode::Subset === $competition->selectionMode) {
            $qb->andWhere(sprintf(
                'EXISTS(SELECT 1 FROM %s cmp_sel WHERE cmp_sel.competition = :cmp_competition_id AND cmp_sel.sportMatch = %s)',
                CompetitionMatchSelection::class,
                $matchAlias,
            ))->setParameter('cmp_competition_id', $competition->id);

            return;
        }

        if (CompetitionMatchSelectionMode::Teams === $competition->selectionMode) {
            $qb->andWhere(sprintf(
                'EXISTS(SELECT 1 FROM %1$s cmp_tf WHERE cmp_tf.competition = :cmp_competition_id AND (cmp_tf.team = %2$s.homeTeam OR cmp_tf.team = %2$s.awayTeam))',
                CompetitionTeamFilter::class,
                $matchAlias,
            ))->setParameter('cmp_competition_id', $competition->id);

            return;
        }

        if (!$competition->includePlayoff) {
            $qb->andWhere(sprintf('%s.isPlayoff = false', $matchAlias));
        }
    }

    /**
     * Row-wise variant for cross-competition queries: `$matchAlias` must be a
     * SportMatch alias and `$competitionAlias` a Competition alias in the same
     * query — each row is kept only when the row's match belongs to the row's
     * competition. Deleted-match filtering stays with the call site (it usually
     * exists already).
     */
    public function applyRowLevelCompetitionMatchFilter(QueryBuilder $qb, string $matchAlias, string $competitionAlias): void
    {
        $qb->andWhere(sprintf(
            '('
            .'(%1$s.selectionMode = :cmp_mode_all AND (%1$s.includePlayoff = true OR %2$s.isPlayoff = false))'
            .' OR EXISTS(SELECT 1 FROM %3$s cmp_sel_row WHERE cmp_sel_row.competition = %1$s AND cmp_sel_row.sportMatch = %2$s)'
            .' OR EXISTS(SELECT 1 FROM %4$s cmp_tf_row WHERE cmp_tf_row.competition = %1$s AND (cmp_tf_row.team = %2$s.homeTeam OR cmp_tf_row.team = %2$s.awayTeam))'
            .')',
            $competitionAlias,
            $matchAlias,
            CompetitionMatchSelection::class,
            CompetitionTeamFilter::class,
        ))->setParameter('cmp_mode_all', CompetitionMatchSelectionMode::All);
    }

    public function forgetSelections(Uuid $competitionId): void
    {
        unset($this->selectionCache[$competitionId->toRfc4122()]);
    }

    public function forgetTeamFilters(Uuid $competitionId): void
    {
        unset($this->filterTeamCache[$competitionId->toRfc4122()]);
    }

    /**
     * Kernel reset (autoconfigured via {@see ResetInterface}) — drops the
     * selection + team-filter caches between requests/tests so stale entries
     * never leak.
     */
    public function reset(): void
    {
        $this->selectionCache = [];
        $this->filterTeamCache = [];
    }

    /**
     * @return array<string, true>
     */
    private function selectedMatchIdSet(Uuid $competitionId): array
    {
        $key = $competitionId->toRfc4122();

        if (!isset($this->selectionCache[$key])) {
            $set = [];

            foreach ($this->selectionRepository->selectedMatchIds($competitionId) as $matchId) {
                $set[$matchId] = true;
            }

            $this->selectionCache[$key] = $set;
        }

        return $this->selectionCache[$key];
    }

    /**
     * @return array<string, true>
     */
    private function filterTeamIdSet(Uuid $competitionId): array
    {
        $key = $competitionId->toRfc4122();

        if (!isset($this->filterTeamCache[$key])) {
            $set = [];

            foreach ($this->teamFilterRepository->teamIdsFor($competitionId) as $teamId) {
                $set[$teamId] = true;
            }

            $this->filterTeamCache[$key] = $set;
        }

        return $this->filterTeamCache[$key];
    }
}
