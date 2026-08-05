<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionSource;
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
 * A competition's scope is the UNION of its {@see CompetitionSource}
 * layers. Each layer names a zdroj zápasů and answers, for THAT zdroj only:
 *
 * - mode All ⇒ all of the layer's source matches, minus playoff matches when
 *   the layer's `includePlayoff = false`, minus deleted matches;
 * - mode Subset ⇒ the layer's explicitly selected matches only (selection wins
 *   over `includePlayoff` — an explicitly selected playoff match counts).
 * - mode Teams ⇒ every match of the layer's source where one of the LAYER's
 *   filter teams plays (home OR away), dynamically — a team match added later
 *   auto-joins; playoff always counts.
 *
 * A match therefore belongs to the competition iff the layer fed by its own
 * source exists and accepts it. Matches of a zdroj the competition does not
 * draw from are out, whatever selection rows may linger.
 *
 * Read queries compose the same semantics via {@see applyCompetitionMatchFilter}
 * (competition-scoped) or {@see applyRowLevelCompetitionMatchFilter}
 * (cross-competition row-wise variant).
 */
class CompetitionMatchProvider implements ResetInterface
{
    /** @var array<string, array<string, array<string, true>>> competition UUID → layer UUID → set of selected match UUIDs */
    private array $selectionCache = [];

    /** @var array<string, array<string, array<string, true>>> competition UUID → layer UUID → set of filter team UUIDs */
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
        // Only the layer fed by the match's OWN zdroj can accept it; a
        // competition that does not draw from that zdroj never includes it.
        $layer = $competition->sourceFor($sportMatch->matchSource->id);

        if (null === $layer) {
            return false;
        }

        $layerKey = $layer->id->toRfc4122();

        if (CompetitionMatchSelectionMode::Subset === $layer->selectionMode) {
            $selected = $this->selectedMatchIdSets($competition->id)[$layerKey] ?? [];

            return isset($selected[$sportMatch->id->toRfc4122()]);
        }

        if (CompetitionMatchSelectionMode::Teams === $layer->selectionMode) {
            $teamIds = $this->filterTeamIdSets($competition->id)[$layerKey] ?? [];

            return isset($teamIds[$sportMatch->homeTeam->id->toRfc4122()])
                || isset($teamIds[$sportMatch->awayTeam->id->toRfc4122()]);
        }

        return $layer->includePlayoff || !$sportMatch->isPlayoff;
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

        $qb->andWhere(sprintf('%s.deletedAt IS NULL', $matchAlias));

        $layers = $competition->sources;

        // A competition with no zdroj includes nothing. It should not exist, but
        // an unsatisfiable predicate is the safe answer — the alternative is a
        // filter that silently matches every match in the database.
        if ([] === $layers) {
            $qb->andWhere('1 = 0');

            return;
        }

        $branches = [];

        foreach ($layers as $index => $layer) {
            $sourceParam = 'cmp_src_'.$index;
            $layerParam = 'cmp_layer_'.$index;
            $sourceMatches = sprintf('%s.matchSource = :%s', $matchAlias, $sourceParam);

            $qb->setParameter($sourceParam, $layer->matchSource->id);

            if (CompetitionMatchSelectionMode::Subset === $layer->selectionMode) {
                $branches[] = sprintf(
                    '(%s AND EXISTS(SELECT 1 FROM %s cmp_sel_%d WHERE cmp_sel_%d.competitionSource = :%s AND cmp_sel_%d.sportMatch = %s))',
                    $sourceMatches,
                    CompetitionMatchSelection::class,
                    $index,
                    $index,
                    $layerParam,
                    $index,
                    $matchAlias,
                );
                $qb->setParameter($layerParam, $layer->id);

                continue;
            }

            if (CompetitionMatchSelectionMode::Teams === $layer->selectionMode) {
                $branches[] = sprintf(
                    '(%1$s AND EXISTS(SELECT 1 FROM %2$s cmp_tf_%3$d WHERE cmp_tf_%3$d.competitionSource = :%4$s AND (cmp_tf_%3$d.team = %5$s.homeTeam OR cmp_tf_%3$d.team = %5$s.awayTeam)))',
                    $sourceMatches,
                    CompetitionTeamFilter::class,
                    $index,
                    $layerParam,
                    $matchAlias,
                );
                $qb->setParameter($layerParam, $layer->id);

                continue;
            }

            $branches[] = $layer->includePlayoff
                ? sprintf('(%s)', $sourceMatches)
                : sprintf('(%s AND %s.isPlayoff = false)', $sourceMatches, $matchAlias);
        }

        $qb->andWhere('('.implode(' OR ', $branches).')');
    }

    /**
     * Row-wise variant for cross-competition queries: `$matchAlias` must be a
     * SportMatch alias and `$competitionAlias` a Competition alias in the same
     * query — each row is kept only when the row's match belongs to the row's
     * competition. Deleted-match filtering stays with the call site (it usually
     * exists already).
     *
     * The whole test is one EXISTS over the row's scope layers: the layer must
     * be fed by the match's OWN zdroj, and must accept the match under its own
     * mode. That inner source equality is what makes this predicate complete —
     * before multi-source, callers had to hand-join `m.matchSource =
     * c.headlineSource` for the result to be correct, an obligation nothing
     * enforced. **Do not add such a join back**: it would pin a multi-source
     * competition to its headline zdroj and silently drop every other layer's
     * matches.
     *
     * Each branch is guarded by the LAYER's own selection mode, exactly like
     * {@see applyCompetitionMatchFilter} and {@see includesIgnoringDeletion}.
     * An un-guarded OR of the three branches would let a leftover selection /
     * team-filter row of a mode the layer no longer uses smuggle a match into a
     * cross-competition surface that the competition-scoped filter (and
     * therefore the match detail page) says is out of scope.
     */
    public function applyRowLevelCompetitionMatchFilter(QueryBuilder $qb, string $matchAlias, string $competitionAlias): void
    {
        $qb->andWhere(sprintf(
            'EXISTS(SELECT 1 FROM %5$s cmp_cs_row'
            .' WHERE cmp_cs_row.competition = %1$s AND cmp_cs_row.matchSource = %2$s.matchSource AND ('
            .'(cmp_cs_row.selectionMode = :cmp_mode_all AND (cmp_cs_row.includePlayoff = true OR %2$s.isPlayoff = false))'
            .' OR (cmp_cs_row.selectionMode = :cmp_mode_subset AND EXISTS(SELECT 1 FROM %3$s cmp_sel_row WHERE cmp_sel_row.competitionSource = cmp_cs_row AND cmp_sel_row.sportMatch = %2$s))'
            .' OR (cmp_cs_row.selectionMode = :cmp_mode_teams AND EXISTS(SELECT 1 FROM %4$s cmp_tf_row WHERE cmp_tf_row.competitionSource = cmp_cs_row AND (cmp_tf_row.team = %2$s.homeTeam OR cmp_tf_row.team = %2$s.awayTeam)))'
            .'))',
            $competitionAlias,
            $matchAlias,
            CompetitionMatchSelection::class,
            CompetitionTeamFilter::class,
            CompetitionSource::class,
        ))
            ->setParameter('cmp_mode_all', CompetitionMatchSelectionMode::All)
            ->setParameter('cmp_mode_subset', CompetitionMatchSelectionMode::Subset)
            ->setParameter('cmp_mode_teams', CompetitionMatchSelectionMode::Teams);
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
     * @return array<string, array<string, true>> layer UUID → set of match UUIDs
     */
    private function selectedMatchIdSets(Uuid $competitionId): array
    {
        $key = $competitionId->toRfc4122();

        return $this->selectionCache[$key] ??= $this->selectionRepository->selectedMatchIdsByLayer($competitionId);
    }

    /**
     * @return array<string, array<string, true>> layer UUID → set of team UUIDs
     */
    private function filterTeamIdSets(Uuid $competitionId): array
    {
        $key = $competitionId->toRfc4122();

        return $this->filterTeamCache[$key] ??= $this->teamFilterRepository->teamIdsByLayer($competitionId);
    }
}
