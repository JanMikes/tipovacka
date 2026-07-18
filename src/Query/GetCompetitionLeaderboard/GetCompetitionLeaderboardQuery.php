<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionLeaderboard;

use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\SportMatch;
use App\Repository\CompetitionRepository;
use App\Repository\LeaderboardTieResolutionRepository;
use App\Repository\MembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionLeaderboardQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private LeaderboardTieResolutionRepository $resolutionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetCompetitionLeaderboard $query): CompetitionLeaderboardResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);
        $memberships = $this->membershipRepository->findActiveByCompetition($competition->id);

        /** @var list<array{userId: string, points: int, evaluated: int, scored: int}> $aggregates */
        $aggregates = $this->entityManager->createQueryBuilder()
            ->select(
                'IDENTITY(g.user) AS userId',
                'SUM(e.totalPoints) AS points',
                'COUNT(e.id) AS evaluated',
                'SUM(CASE WHEN e.totalPoints > 0 THEN 1 ELSE 0 END) AS scored',
            )
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin(Guess::class, 'g', 'WITH', 'g.id = e.guess')
            ->where('g.competition = :competitionId')
            ->andWhere('g.deletedAt IS NULL')
            ->groupBy('g.user')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getArrayResult();

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
        /** @var list<array{userId: string, exact: int}> $exactAggregates */
        $exactAggregates = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.user) AS userId', 'COUNT(rp.id) AS exact')
            ->from(GuessEvaluationRulePoints::class, 'rp')
            ->innerJoin(GuessEvaluation::class, 'e', 'WITH', 'e.id = rp.evaluation')
            ->innerJoin(Guess::class, 'g', 'WITH', 'g.id = e.guess')
            ->where('g.competition = :competitionId')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('rp.ruleIdentifier = :exactId')
            ->andWhere('rp.points > 0')
            ->groupBy('g.user')
            ->setParameter('competitionId', $competition->id)
            ->setParameter('exactId', 'exact_score')
            ->getQuery()
            ->getArrayResult();

        $exactByUser = [];

        foreach ($exactAggregates as $row) {
            $exactByUser[$row['userId']] = (int) $row['exact'];
        }

        // Current scoring streak: trailing run of non-zero evaluations by match kickoff (newest first).
        /** @var list<array{userId: string, points: int}> $streakRows */
        $streakRows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.user) AS userId', 'e.totalPoints AS points')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin(Guess::class, 'g', 'WITH', 'g.id = e.guess')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.id = g.sportMatch')
            ->where('g.competition = :competitionId')
            ->andWhere('g.deletedAt IS NULL')
            ->orderBy('g.user', 'ASC')
            ->addOrderBy('m.kickoffAt', 'DESC')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getArrayResult();

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

            $finalRows[] = new LeaderboardRow(
                userId: $row['userId'],
                nickname: $row['nickname'],
                fullName: $row['fullName'],
                totalPoints: $row['points'],
                rank: null !== $override ? $override->rank : $row['rank'],
                isTieResolvedOverride: null !== $override,
                evaluatedCount: $evaluated,
                scoredCount: $scored,
                exactCount: $exact,
                partialCount: max(0, $scored - $exact),
                accuracyPercent: $evaluated > 0 ? (int) round($scored * 100 / $evaluated) : 0,
                streak: $streakByUser[$userKey] ?? 0,
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
            matchSourceFinished: $competition->matchSource->isFinished,
        );
    }
}
