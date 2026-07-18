<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\SportMatch;
use App\Enum\CompetitionMatchSelectionMode;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionRepository;
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
 *
 * Read queries compose the same semantics via {@see applyCompetitionMatchFilter}
 * (competition-scoped) or {@see applyRowLevelCompetitionMatchFilter}
 * (cross-competition row-wise variant).
 */
class CompetitionMatchProvider implements ResetInterface
{
    /** @var array<string, array<string, true>> competition UUID → set of selected match UUIDs */
    private array $selectionCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompetitionRepository $competitionRepository,
        private readonly CompetitionMatchSelectionRepository $selectionRepository,
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

    public function includes(Competition $competition, SportMatch $sportMatch): bool
    {
        if (null !== $sportMatch->deletedAt) {
            return false;
        }

        if (!$sportMatch->matchSource->id->equals($competition->matchSource->id)) {
            return false;
        }

        if (CompetitionMatchSelectionMode::Subset === $competition->selectionMode) {
            return isset($this->selectedMatchIdSet($competition->id)[$sportMatch->id->toRfc4122()]);
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
            '((%1$s.selectionMode = :cmp_mode_all AND (%1$s.includePlayoff = true OR %2$s.isPlayoff = false)) OR EXISTS(SELECT 1 FROM %3$s cmp_sel_row WHERE cmp_sel_row.competition = %1$s AND cmp_sel_row.sportMatch = %2$s))',
            $competitionAlias,
            $matchAlias,
            CompetitionMatchSelection::class,
        ))->setParameter('cmp_mode_all', CompetitionMatchSelectionMode::All);
    }

    public function forgetSelections(Uuid $competitionId): void
    {
        unset($this->selectionCache[$competitionId->toRfc4122()]);
    }

    /**
     * Kernel reset (autoconfigured via {@see ResetInterface}) — drops the
     * selection cache between requests/tests so stale selections never leak.
     */
    public function reset(): void
    {
        $this->selectionCache = [];
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
}
