<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionLeaderboard;

use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\SportMatch;
use App\Enum\LeaderboardTimeFilter;
use App\Repository\CompetitionRepository;
use App\Repository\LeaderboardSnapshotRepository;
use App\Repository\LeaderboardTieResolutionRepository;
use App\Repository\MembershipRepository;
use App\Rule\ExactScoreRule;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\PragueCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionLeaderboardQuery
{
    /** „Posledních 7 dní" rolling window length. */
    private const string WINDOW_LAST_7_DAYS = '-7 days';

    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private LeaderboardTieResolutionRepository $resolutionRepository,
        private LeaderboardSnapshotRepository $snapshotRepository,
        private CompetitionMatchProvider $matchProvider,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetCompetitionLeaderboard $query): CompetitionLeaderboardResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);
        $memberships = $this->membershipRepository->findActiveByCompetition($competition->id);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $aggregatesQb = $this->entityManager->createQueryBuilder()
            ->select(
                'IDENTITY(g.user) AS userId',
                'SUM(e.totalPoints) AS points',
                'COUNT(e.id) AS evaluated',
                'SUM(CASE WHEN e.totalPoints > 0 THEN 1 ELSE 0 END) AS scored',
            )
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin(Guess::class, 'g', 'WITH', 'g.id = e.guess')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.id = g.sportMatch')
            ->where('g.competition = :competitionId')
            ->andWhere('g.deletedAt IS NULL')
            ->groupBy('g.user')
            ->setParameter('competitionId', $competition->id);
        $this->matchProvider->applyCompetitionMatchFilter($aggregatesQb, 'm', $competition);
        $this->applyTimeWindow($aggregatesQb, 'm', $query->filter, $now);

        /** @var list<array{userId: string, points: int, evaluated: int, scored: int}> $aggregates */
        $aggregates = $aggregatesQb->getQuery()->getArrayResult();

        $pointsByUser = [];
        $evaluatedByUser = [];
        $scoredByUser = [];

        foreach ($aggregates as $row) {
            $pointsByUser[$row['userId']] = (int) $row['points'];
            $evaluatedByUser[$row['userId']] = (int) $row['evaluated'];
            $scoredByUser[$row['userId']] = (int) $row['scored'];
        }

        // Exact-score hits per user (separate query so the rule-points join does not
        // multiply the totalPoints SUM above).
        $exactQb = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.user) AS userId', 'COUNT(rp.id) AS exact')
            ->from(GuessEvaluationRulePoints::class, 'rp')
            ->innerJoin(GuessEvaluation::class, 'e', 'WITH', 'e.id = rp.evaluation')
            ->innerJoin(Guess::class, 'g', 'WITH', 'g.id = e.guess')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.id = g.sportMatch')
            ->where('g.competition = :competitionId')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('rp.ruleIdentifier = :exactId')
            ->andWhere('rp.points > 0')
            ->groupBy('g.user')
            ->setParameter('competitionId', $competition->id)
            ->setParameter('exactId', ExactScoreRule::IDENTIFIER);
        $this->matchProvider->applyCompetitionMatchFilter($exactQb, 'm', $competition);
        $this->applyTimeWindow($exactQb, 'm', $query->filter, $now);

        /** @var list<array{userId: string, exact: int}> $exactAggregates */
        $exactAggregates = $exactQb->getQuery()->getArrayResult();

        $exactByUser = [];

        foreach ($exactAggregates as $row) {
            $exactByUser[$row['userId']] = (int) $row['exact'];
        }

        // Current scoring streak: trailing run of non-zero evaluations by match kickoff (newest first).
        $streakQb = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.user) AS userId', 'e.totalPoints AS points')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin(Guess::class, 'g', 'WITH', 'g.id = e.guess')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.id = g.sportMatch')
            ->where('g.competition = :competitionId')
            ->andWhere('g.deletedAt IS NULL')
            ->orderBy('g.user', 'ASC')
            ->addOrderBy('m.kickoffAt', 'DESC')
            ->setParameter('competitionId', $competition->id);
        $this->matchProvider->applyCompetitionMatchFilter($streakQb, 'm', $competition);
        $this->applyTimeWindow($streakQb, 'm', $query->filter, $now);

        /** @var list<array{userId: string, points: int}> $streakRows */
        $streakRows = $streakQb->getQuery()->getArrayResult();

        $streakByUser = [];
        $streakClosed = [];

        foreach ($streakRows as $row) {
            $userKey = $row['userId'];

            if ($streakClosed[$userKey] ?? false) {
                continue;
            }

            if ((int) $row['points'] > 0) {
                $streakByUser[$userKey] = ($streakByUser[$userKey] ?? 0) + 1;
            } else {
                $streakClosed[$userKey] = true;
            }
        }

        $resolutions = $this->resolutionRepository->findForCompetition($competition->id);

        // Δ is all-time only: a windowed board re-ranks by windowed points, so an
        // all-time snapshot Δ would be meaningless — the UI then hides the column.
        $showDelta = LeaderboardTimeFilter::AllTime === $query->filter;
        $previousRanks = $showDelta
            ? $this->snapshotRepository->latestBefore($competition->id, PragueCalendar::day($now))
            : [];
        $hasHistory = [] !== $previousRanks;

        $baseRows = [];

        foreach ($memberships as $membership) {
            $user = $membership->user;
            $userKey = $user->id->toRfc4122();
            $hasNickname = null !== $user->nickname && '' !== $user->nickname;
            $hasFullName = '' !== $user->fullName;

            $baseRows[] = [
                'userId' => $user->id,
                'nickname' => $user->displayName,
                'fullName' => ($hasNickname && $hasFullName) ? $user->fullName : null,
                'points' => $pointsByUser[$userKey] ?? 0,
            ];
        }

        usort(
            $baseRows,
            static fn (array $a, array $b): int => $b['points'] <=> $a['points']
                ?: strcmp($a['nickname'], $b['nickname']),
        );

        $rows = [];

        foreach ($baseRows as $index => $row) {
            $rank = 1;

            foreach ($baseRows as $other) {
                if ($other['points'] > $row['points']) {
                    ++$rank;
                }
            }

            $rows[] = [
                'userId' => $row['userId'],
                'nickname' => $row['nickname'],
                'fullName' => $row['fullName'],
                'points' => $row['points'],
                'rank' => $rank,
                'index' => $index,
            ];
        }

        $finalRows = [];

        foreach ($rows as $row) {
            $userKey = $row['userId']->toRfc4122();
            $override = $resolutions[$userKey] ?? null;

            $evaluated = $evaluatedByUser[$userKey] ?? 0;
            $scored = $scoredByUser[$userKey] ?? 0;
            $exact = $exactByUser[$userKey] ?? 0;

            // Tie-resolution overrides stay authoritative for the current rank, so
            // Δ is measured against the rank actually shown.
            $currentRank = null !== $override ? $override->rank : $row['rank'];

            $delta = null;
            $deltaIsNew = false;

            if ($hasHistory) {
                $previous = $previousRanks[$userKey] ?? null;

                if (null !== $previous) {
                    $delta = $previous['rank'] - $currentRank;
                } else {
                    $deltaIsNew = true;
                }
            }

            $finalRows[] = new LeaderboardRow(
                userId: $row['userId'],
                nickname: $row['nickname'],
                fullName: $row['fullName'],
                totalPoints: $row['points'],
                rank: $currentRank,
                isTieResolvedOverride: null !== $override,
                evaluatedCount: $evaluated,
                scoredCount: $scored,
                exactCount: $exact,
                partialCount: max(0, $scored - $exact),
                accuracyPercent: $evaluated > 0 ? (int) round($scored * 100 / $evaluated) : 0,
                streak: $streakByUser[$userKey] ?? 0,
                delta: $delta,
                deltaIsNew: $deltaIsNew,
            );
        }

        usort(
            $finalRows,
            static fn (LeaderboardRow $a, LeaderboardRow $b): int => $a->rank <=> $b->rank
                ?: $b->totalPoints <=> $a->totalPoints
                ?: strcmp($a->nickname, $b->nickname),
        );

        return new CompetitionLeaderboardResult(
            rows: $finalRows,
            matchSourceCompleted: $competition->matchSource->isCompleted,
            showDelta: $showDelta,
        );
    }

    /**
     * „Posledních 7 dní": keep only evaluations whose match kicked off within the
     * rolling 7-day window ending now. The boundary is an instant (both sides
     * UTC-stored), so it is derived directly from the injected clock — the Prague
     * framing only matters for day-labelled snapshots, not this rolling sum.
     * All-time applies no window.
     */
    private function applyTimeWindow(QueryBuilder $qb, string $matchAlias, LeaderboardTimeFilter $filter, \DateTimeImmutable $now): void
    {
        if (LeaderboardTimeFilter::Last7Days === $filter) {
            $qb->andWhere(sprintf('%s.kickoffAt >= :lbWindowStart', $matchAlias))
                ->setParameter('lbWindowStart', $now->modify(self::WINDOW_LAST_7_DAYS));
        }
    }
}
