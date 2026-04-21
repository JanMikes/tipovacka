<?php

declare(strict_types=1);

namespace App\Query\GetGroupLeaderboard;

use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Repository\GroupRepository;
use App\Repository\LeaderboardTieResolutionRepository;
use App\Repository\MembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetGroupLeaderboardQuery
{
    public function __construct(
        private GroupRepository $groupRepository,
        private MembershipRepository $membershipRepository,
        private LeaderboardTieResolutionRepository $resolutionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetGroupLeaderboard $query): GroupLeaderboardResult
    {
        $group = $this->groupRepository->get($query->groupId);
        $memberships = $this->membershipRepository->findActiveByGroup($group->id);

        /** @var list<array{userId: string, points: int}> $aggregates */
        $aggregates = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.user) AS userId', 'SUM(e.totalPoints) AS points')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin(Guess::class, 'g', 'WITH', 'g.id = e.guess')
            ->where('g.group = :groupId')
            ->andWhere('g.deletedAt IS NULL')
            ->groupBy('g.user')
            ->setParameter('groupId', $group->id)
            ->getQuery()
            ->getArrayResult();

        $pointsByUser = [];

        foreach ($aggregates as $row) {
            $pointsByUser[$row['userId']] = (int) $row['points'];
        }

        $resolutions = $this->resolutionRepository->findForGroup($group->id);

        $baseRows = [];

        foreach ($memberships as $membership) {
            $userKey = $membership->user->id->toRfc4122();
            $baseRows[] = [
                'userId' => $membership->user->id,
                'nickname' => $membership->user->displayName,
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
                'points' => $row['points'],
                'rank' => $rank,
                'index' => $index,
            ];
        }

        $finalRows = [];

        foreach ($rows as $row) {
            $userKey = $row['userId']->toRfc4122();
            $override = $resolutions[$userKey] ?? null;

            $finalRows[] = new LeaderboardRow(
                userId: $row['userId'],
                nickname: $row['nickname'],
                totalPoints: $row['points'],
                rank: null !== $override ? $override->rank : $row['rank'],
                isTieResolvedOverride: null !== $override,
            );
        }

        usort(
            $finalRows,
            static fn (LeaderboardRow $a, LeaderboardRow $b): int => $a->rank <=> $b->rank
                ?: $b->totalPoints <=> $a->totalPoints
                ?: strcmp($a->nickname, $b->nickname),
        );

        return new GroupLeaderboardResult(
            rows: $finalRows,
            tournamentFinished: $group->tournament->isFinished,
        );
    }
}
