<?php

declare(strict_types=1);

namespace App\Query\GetMatchPickDistribution;

use App\Entity\Guess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMatchPickDistributionQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetMatchPickDistribution $query): MatchPickDistributionResult
    {
        /** @var array{home: int|string|null, draw: int|string|null, away: int|string|null, total: int|string} $row */
        $row = $this->entityManager->createQueryBuilder()
            ->select(
                'SUM(CASE WHEN g.homeScore > g.awayScore THEN 1 ELSE 0 END) AS home',
                'SUM(CASE WHEN g.homeScore = g.awayScore THEN 1 ELSE 0 END) AS draw',
                'SUM(CASE WHEN g.homeScore < g.awayScore THEN 1 ELSE 0 END) AS away',
                'COUNT(g.id) AS total',
            )
            ->from(Guess::class, 'g')
            ->where('g.group = :groupId')
            ->andWhere('g.sportMatch = :matchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('groupId', $query->groupId)
            ->setParameter('matchId', $query->sportMatchId)
            ->getQuery()
            ->getSingleResult();

        $home = (int) ($row['home'] ?? 0);
        $draw = (int) ($row['draw'] ?? 0);
        $away = (int) ($row['away'] ?? 0);
        $total = (int) $row['total'];

        return new MatchPickDistributionResult(
            homeWinCount: $home,
            drawCount: $draw,
            awayWinCount: $away,
            total: $total,
            homeWinPercent: $total > 0 ? (int) round($home * 100 / $total) : 0,
            drawPercent: $total > 0 ? (int) round($draw * 100 / $total) : 0,
            awayWinPercent: $total > 0 ? (int) round($away * 100 / $total) : 0,
        );
    }
}
